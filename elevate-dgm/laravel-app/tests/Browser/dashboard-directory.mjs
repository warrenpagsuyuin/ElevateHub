import {chromium,expect} from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
const root=process.cwd(),dir=path.join(root,'storage/framework/testing/dashboard-directory');
const browser=await chromium.launch({channel:'msedge',headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1000}});
await page.route('**/*',async route=>{
 const url=new URL(route.request().url());
 if(route.request().method()!=='GET')return route.fulfill({status:409,body:'Read-only preview'});
 if(url.pathname.startsWith('/admin/dashboard')){
 const type=url.pathname.split('/')[3]||'dashboard',category=url.searchParams.get('category');
 return route.fulfill({contentType:'text/html',body:await fs.readFile(path.join(dir,type+(category?'-'+category:'')+'.html'),'utf8')});
 }
 const asset=path.resolve(root,'public','.'+decodeURIComponent(url.pathname));
 if(asset.startsWith(path.join(root,'public')+path.sep))try{return route.fulfill({body:await fs.readFile(asset),contentType:asset.endsWith('.css')?'text/css':asset.endsWith('.js')?'text/javascript':asset.endsWith('.svg')?'image/svg+xml':'application/octet-stream'});}catch{}
 return route.fulfill({status:404,body:''});
});
try {
 await page.goto('http://127.0.0.1:8000/admin/dashboard');
 await expect(page.locator('.summary-number')).toHaveCount(4);
 await page.screenshot({path:path.join(dir,'dashboard-desktop.png'),fullPage:true});
 const total=page.getByRole('link',{name:'View all 178 DGroup Members',exact:true});
 await total.focus(); await expect(total).toBeFocused();
 await total.press('Enter');
 await expect(page.getByRole('status')).toContainText('Showing 178 members');
 await expect(page.locator('tbody tr')).toHaveCount(25);
 for(const width of [1440,768,390]){
 await page.setViewportSize({width,height:900});
 await page.screenshot({path:path.join(dir,'members-'+width+'.png'),fullPage:true});
 expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width+1);
 }
 await page.addScriptTag({path:path.join(root,'node_modules/axe-core/axe.min.js')});
 const axe=await page.evaluate(async()=>await window.axe.run('.directory-page',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));
 expect(axe.violations.map(v=>({id:v.id,targets:v.nodes.map(n=>n.target)}))).toEqual([]);
 await page.getByRole('link',{name:'Back to dashboard',exact:false}).click();
 await page.getByRole('link',{name:'View 0 NCP DGroup Members',exact:true}).click();
 await expect(page.getByText('No NCP members yet',{exact:true})).toBeVisible();
 console.log('PASS: member card keyboard navigation, 178 rows paginated, NCP empty state, desktop/tablet/mobile layout, WCAG AA scan on rendered read-only HTML.');
}finally{await browser.close();}
