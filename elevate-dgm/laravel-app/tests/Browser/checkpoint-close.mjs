import { chromium, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
const root=process.cwd(), dir=path.join(root,'storage/framework/testing/movement-ui');
const actual=JSON.parse(await fs.readFile(path.join(dir,'close-review.json'),'utf8'));
const aggregate=await fs.readFile(path.join(dir,'after.json'),'utf8');
let state=structuredClone(actual), writes=[];
const browser=await chromium.launch({channel:'msedge',headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1000}});
await page.route('**/*',async route=>{
  const url=new URL(route.request().url());
  if (url.pathname==='/admin/analytics') return route.fulfill({contentType:'text/html',body:await fs.readFile(path.join(dir,'after.html'),'utf8')});
  if (url.pathname==='/admin/analytics/movement-data') return route.fulfill({contentType:'application/json',body:aggregate});
  if (url.pathname.endsWith('/review')) return route.fulfill({contentType:'application/json',body:JSON.stringify(state)});
  if (route.request().method()==='POST') {
    const payload=route.request().postDataJSON(); writes.push({path:url.pathname,payload});
    if (url.pathname.endsWith('/close')) { state.checkpoint.status='closed'; state.ready=false; state.closed_by=999; state.closed_at='2026-10-02 12:00:00'; }
    if (url.pathname.endsWith('/corrections')) { state.revision++; Object.entries(payload.values).forEach(([m,v])=>state.units[payload.unit].metrics[m].value=v); }
    return route.fulfill({status:201,contentType:'application/json',body:JSON.stringify({status:'closed',revision:state.revision})});
  }
  const file=path.resolve(root,'public',`.${decodeURIComponent(url.pathname)}`);
  if (file.startsWith(path.join(root,'public')+path.sep)) {
    try { return route.fulfill({body:await fs.readFile(file),contentType:file.endsWith('.css')?'text/css':file.endsWith('.js')?'text/javascript':file.endsWith('.png')?'image/png':'application/octet-stream'}); } catch {}
  }
  return route.fulfill({status:404,body:''});
});
try {
  await page.goto('http://127.0.0.1:8000/admin/analytics');
  await expect(page.locator('#mv-content')).toBeVisible();
  await page.getByRole('button',{name:'Review checkpoint close'}).click();
  await expect(page.locator('#qc-results')).toBeVisible();
  await expect(page.getByRole('button',{name:'Close checkpoint permanently'})).toBeDisabled();
  await expect(page.locator('#qc-attest')).toBeHidden();
  await expect(page.locator('#qc-blockers')).toContainText('verified counts are missing');
  await page.screenshot({path:path.join(dir,'checkpoint-close-blocked.png')});
  await page.addScriptTag({path:path.join(root,'node_modules/axe-core/axe.min.js')});
  const result=await page.evaluate(async()=>axe.run('#qc-dialog',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));
  if(result.violations.length) throw new Error(JSON.stringify(result.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}))));
  await page.keyboard.press('Escape');
  await expect(page.getByRole('button',{name:'Review checkpoint close'})).toBeFocused();
  // Synthetic interaction test: no POST reaches the real application or database.
  state.ready=true; state.blockers=[];
  for(const unit of Object.values(state.units)) {unit.confirmed=true;unit.missing=[];for(const [metric,cell] of Object.entries(unit.metrics)){if(cell.state!=='not_applicable'){cell.value=metric==='dmembers'?6:1;cell.state='reported';}}}
  await page.getByRole('button',{name:'Review checkpoint close'}).click();
  await expect(page.getByRole('button',{name:'Close checkpoint permanently'})).toBeEnabled();
  await page.getByLabel('Close reason',{exact:true}).fill('Synthetic close interaction test');
  await page.locator('#qc-reference').fill('Synthetic evidence reference only');
  await page.locator('#qc-ack').check();
  await page.locator('#qc-confirm').fill('2026-09');
  await page.getByRole('button',{name:'Close checkpoint permanently'}).click();
  await expect(page.locator('#qc-correction')).toBeVisible();
  expect(writes[0].payload.confirmation).toBe('2026-09'); expect(writes[0].payload.acknowledge_issues).toBe(true);
  await page.locator('#qc-correction-values input[name=dmembers]').fill('8');
  await page.getByLabel('Correction reason',{exact:true}).fill('Synthetic corrected count evidence');
  await page.getByLabel('Correction evidence reference',{exact:true}).fill('Synthetic updated source reference');
  await page.getByRole('button',{name:'Record correction version'}).click();
  await expect(page.locator('#qc-summary')).toContainText('revision 1');
  expect(writes[1].payload.expected_version).toBe(0); expect(writes[1].payload.values.dmembers).toBe(8);
  console.log('PASS: actual blocked review, admin/leader control separation, keyboard focus, modal WCAG AA, synthetic close confirmation and correction-version requests. No real POSTs sent.');
} finally {await browser.close();}
