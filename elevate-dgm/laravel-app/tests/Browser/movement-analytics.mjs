import { chromium, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
const root=process.cwd();
const output=path.join(root,'storage/framework/testing/movement-ui');
const current=JSON.parse(await fs.readFile(path.join(output,'after.json'),'utf8'));
const browser=await chromium.launch({channel:'msedge',headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1000},deviceScaleFactor:1});
let variant='after', fail=false, fixture=false;
let releaseData=null, heldData=null;
const errors=[];
page.on('pageerror', e => errors.push(e.message));
const filled=structuredClone(current);
filled.activity.scope='SYNTHETIC UI FIXTURE · not production data';
filled.forecasts.units['main.pasig'].dmembers={available:true,points:3,next:'Dec 2026',low:80,high:120,backtest:{predicted:100,actual:95,absolute_error:5,no_change_error:15}};
for (const unit of Object.values(filled.units)) {
  for (const [index,period] of Object.values(filled.periods).entries()) {
    for (const metric of Object.keys(unit.metrics)) {
      const cell={...period.units[unit.id][metric],value:metric==='campus' && unit.group==='ICP' ? null : (index+1)*10+unit.id,state:metric==='campus' && unit.group==='ICP' ? 'not_applicable':'reported',source:'synthetic_fixture'};
      period.units[unit.id][metric]=cell;
    }
  }
  for (const metric of Object.keys(unit.metrics)) unit.metrics[metric]={...filled.periods['2026-09'].units[unit.id][metric],changes:{baseline:{variance:30,pct:20},previous:{variance:10,pct:5}},status:'Growing'};
  unit.status='Growing';
}
await page.route('**/*',async route => {
  const url=new URL(route.request().url());
  if (url.pathname==='/admin/analytics') return route.fulfill({contentType:'text/html',body:await fs.readFile(path.join(output,`${variant}.html`),'utf8')});
  if (url.pathname==='/api/analytics-data') return route.fulfill({contentType:'application/json',body:await fs.readFile(path.join(output,'before.json'),'utf8')});
  if (url.pathname==='/admin/analytics/movement-data') {
    if (heldData) await heldData;
    if (fail) return route.fulfill({status:503,body:'Unavailable'});
    const data=url.searchParams.get('sample')==='1'?JSON.parse(await fs.readFile(path.join(output,'sample.json'),'utf8')):structuredClone(fixture ? filled : current);
    data.group=url.searchParams.get('group') || null;
    if (url.searchParams.has('checkpoint')) data.checkpoint.key=url.searchParams.get('checkpoint');
    return route.fulfill({contentType:'application/json',body:JSON.stringify(data)});
  }
  if (url.hostname==='cdn.plot.ly') return route.continue();
  const assetPath=path.resolve(root,'public',`.${decodeURIComponent(url.pathname)}`);
  if (assetPath.startsWith(path.join(root,'public')+path.sep)) {
    try { const body=await fs.readFile(assetPath); return route.fulfill({body,contentType:assetPath.endsWith('.css')?'text/css':assetPath.endsWith('.js')?'text/javascript':assetPath.endsWith('.png')?'image/png':'application/octet-stream'}); } catch {}
  }
  return route.fulfill({status:404,body:''});
});
try {
  try { await fs.access(path.join(output,'before.html')); await fs.access(path.join(output,'before.json')); variant='before'; } catch {}
  if (variant==='before') {
    await page.goto('http://127.0.0.1:8000/admin/analytics');
    await page.locator('.analytics-section').first().waitFor();
    await page.screenshot({path:path.join(output,'before-desktop.png'),fullPage:true});
    variant='after';
  }
  const renderStarted=performance.now();
  await page.goto('http://127.0.0.1:8000/admin/analytics');
  await expect(page.locator('#mv-content')).toBeVisible();
  await fs.writeFile(path.join(output,'browser-performance.json'),JSON.stringify({intercepted_page_ready_ms:Math.round(performance.now()-renderStarted),note:'Browser rendering with intercepted JSON; see server-performance.json for actual endpoint timings.'},null,2));
  await expect(page.locator('#mv-kpis')).toContainText('177');
  await expect(page.locator('#mv-kpis')).toContainText('28');
  await expect(page.locator('#mv-kpis')).toContainText('Live');
  await expect(page.locator('.mv-chart-bar')).not.toHaveCount(0);
  await expect(page.locator('#mv-completeness')).toContainText('2 additional units');
  await expect(page.locator('#mv-ranking')).toContainText('Pasig');
  await expect(page.locator('#mv-forecasts')).toContainText('More history');
  await page.screenshot({path:path.join(output,'after-desktop.png'),fullPage:true});
  await page.getByRole('button',{name:'Show all 23 units'}).click();
  await page.getByRole('button',{name:/Explore Pasig,/}).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await expect(page.locator('#mv-drawer-body')).toContainText('Checkpoint history');
  await page.screenshot({path:path.join(output,'unit-drawer.png')});
  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog')).not.toBeVisible();
  await expect(page.getByRole('button',{name:/Explore Pasig,/})).toBeFocused();
  await page.getByRole('button',{name:'How this works',exact:true}).click();
  await expect(page.locator('#mv-drawer-body')).toContainText('No accuracy result is claimed');
  await page.getByRole('button',{name:'Close details'}).click();
  await page.locator('#mv-full-data summary').click();
  await expect(page.locator('#mv-pivot tbody tr')).toHaveCount(current.pivot.rows.length);
  await expect(page.locator('#mv-pivot tbody tr').first().locator('td').first()).toHaveText(String(current.pivot.rows[0][0]));
  await page.locator('#mv-full-data summary').click();
  await page.getByLabel('Movement group',{exact:true}).selectOption('MAIN');
  await page.getByRole('button',{name:'Apply filters'}).click();
  await expect(page).toHaveURL(/group=MAIN/);
  await expect(page.locator('#mv-content')).toBeVisible();
  await page.getByRole('button',{name:'DGroups',exact:true}).click();
  await expect(page).toHaveURL(/metric=dgroups/);
  await expect(page.getByRole('button',{name:'DGroups',exact:true})).toHaveAttribute('aria-pressed','true');
  await page.goBack();
  await expect(page.locator('#mv-content')).toBeVisible();
  await expect(page.getByRole('button',{name:'DMembers',exact:true})).toHaveAttribute('aria-pressed','true');
  await page.setViewportSize({width:820,height:1180});
  await page.goto('http://127.0.0.1:8000/admin/analytics');
  await expect(page.locator('#mv-content')).toBeVisible();
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth);
  if (overflow) throw new Error('Tablet layout overflows horizontally');
  await page.screenshot({path:path.join(output,'after-tablet.png'),fullPage:true});
  await page.addScriptTag({path:path.join(root,'node_modules/axe-core/axe.min.js')});
  const accessibility=await page.evaluate(async()=>await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));
  await fs.writeFile(path.join(output,'accessibility.json'),JSON.stringify(accessibility.violations,null,2));
  if (accessibility.violations.length) throw new Error('Accessibility violations: '+accessibility.violations.map(v=>v.id).join(', '));
  await page.setViewportSize({width:768,height:1024});
  if (await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth)) throw new Error('768px tablet layout overflows');
  heldData=new Promise(resolve=>{releaseData=resolve;});
  await page.getByRole('button',{name:'Apply filters'}).focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('#movement-app')).toHaveAttribute('aria-busy','true');
  await expect(page.locator('#mv-status')).toContainText('Loading movement analytics');
  await expect(page.locator('#mv-export')).toHaveAttribute('aria-disabled','true');
  releaseData(); heldData=null;
  await expect(page.locator('#mv-content')).toBeVisible();
  await page.getByText('Resolve ncp.visayas ? 2025-12',{exact:true}).click();
  await expect(page.locator('.mv-resolution input:checked')).toHaveCount(0);
  await expect(page.locator('.mv-resolution')).toContainText('358');
  await expect(page.locator('.mv-resolution')).toContainText('472');
  const resolutionScan=await page.evaluate(async()=>await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));
  if(resolutionScan.violations.length) throw new Error('Resolution accessibility violations: '+resolutionScan.violations.map(v=>v.id).join(', '));
  fail=true;
  await page.getByRole('button',{name:'Apply filters'}).click();
  await expect(page.locator('#mv-retry')).toBeVisible();
  await expect(page.locator('#mv-content')).not.toBeVisible();
  await expect(page.locator('#mv-export')).toHaveAttribute('aria-disabled','true');
  fail=false;
  await page.getByRole('button',{name:'Try again'}).click();
  await expect(page.locator('#mv-content')).toBeVisible();
  fixture=true;
  await page.setViewportSize({width:1440,height:1000});
  await page.reload();
  await expect(page.locator('.mv-chart-bar')).not.toHaveCount(0);
  await expect(page.locator('#mv-forecasts')).toContainText('80–120');
  await page.addScriptTag({path:path.join(root,'node_modules/axe-core/axe.min.js')});
  const populatedScan=await page.evaluate(async()=>await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));
  await fs.writeFile(path.join(output,'accessibility-populated.json'),JSON.stringify(populatedScan.violations,null,2));
  if (populatedScan.violations.length) throw new Error('Populated accessibility violations: '+populatedScan.violations.map(v=>v.id).join(', '));
  await page.screenshot({path:path.join(output,'synthetic-populated-desktop.png'),fullPage:true});
  await page.goto('http://127.0.0.1:8000/admin/analytics?sample=1');
  await expect(page.locator('#mv-sample-banner')).toBeVisible();
  await expect(page.locator('#mv-export')).toHaveAttribute('aria-disabled','true');
  await expect(page.locator('#qc-workflow')).not.toBeVisible();
  await expect(page.locator('#mv-pipeline')).not.toBeVisible();
  await expect(page.locator('#mv-kpis')).toContainText('SAMPLE DATA');
  await page.screenshot({path:path.join(output,'sample-preview.png'),fullPage:true});
  if (errors.length) throw new Error('Browser script errors: '+errors.join('; '));
  console.log('PASS: before/after captures, live empty state, populated chart fixture, drawer/Escape/focus, method drawer, pivot, URL filters/history, tablet overflow, WCAG AA scan, retry and export error state.');
} finally { await browser.close(); }
