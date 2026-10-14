import { chromium, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
const root=process.cwd(), dir=path.join(root,'storage/framework/testing/movement-ui');
const html=await fs.readFile(path.join(dir,'after.html'),'utf8');
const data=await fs.readFile(path.join(dir,'after.json'));
const browser=await chromium.launch({channel:'msedge',headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1000}});
await page.addInitScript(()=>{
  new MutationObserver((_,observer)=>{
    const content=document.getElementById('mv-content');
    if (content && !content.hidden) {
      requestAnimationFrame(()=>{window.movementReadyMs=performance.now();});
      observer.disconnect();
    }
  }).observe(document,{subtree:true,attributes:true,childList:true});
});
await page.route('**/*',async route=>{
  const url=new URL(route.request().url());
  if(url.pathname==='/admin/analytics') return route.fulfill({contentType:'text/html',body:html});
  if(url.pathname==='/admin/analytics/movement-data') return route.fulfill({contentType:'application/json',body:data});
  const file=path.resolve(root,'public',`.${decodeURIComponent(url.pathname)}`);
  if(file.startsWith(path.join(root,'public')+path.sep)) {
    try {return route.fulfill({body:await fs.readFile(file),contentType:file.endsWith('.css')?'text/css':file.endsWith('.js')?'application/javascript':file.endsWith('.png')?'image/png':'application/octet-stream'});} catch {}
  }
  return route.fulfill({status:404,body:''});
});
try {
  const samples=[];
  for(let i=0;i<3;i++) {
    await page.goto('http://127.0.0.1:8000/admin/analytics');
    await expect(page.locator('#mv-content')).toBeVisible();
    await page.waitForFunction(()=>window.movementReadyMs>0);
    samples.push(await page.evaluate(()=>({ready_ms:Math.round(window.movementReadyMs),navigation:performance.getEntriesByType('navigation')[0].toJSON(),resources:performance.getEntriesByType('resource').map(r=>({name:r.name,duration_ms:Math.round(r.duration)}))})));
  }
  const result={note:'Built assets and actual local aggregate JSON intercepted by Playwright; excludes HTTP server latency.',samples};
  await fs.writeFile(path.join(dir,'render-performance.json'),JSON.stringify(result,null,2));
  console.log(JSON.stringify({ready_ms:samples.map(s=>s.ready_ms),note:result.note}));
} finally {await browser.close();}
