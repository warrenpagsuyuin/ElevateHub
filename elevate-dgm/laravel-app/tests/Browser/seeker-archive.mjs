import {chromium, expect} from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
const root=process.cwd(), output=path.join(root,'storage/framework/testing/seeker-archive');
const browser=await chromium.launch({channel:'msedge',headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1000},deviceScaleFactor:1});
const errors=[]; page.on('pageerror',e=>errors.push(e.message));
let before=false;
await page.route('**/*',async route=>{
    const url=new URL(route.request().url());
    if (route.request().method()!=='GET') return route.fulfill({status:409,body:'Read-only browser preview: mutations are tested with an isolated database.'});
    if(url.pathname==='/admin/seekers') {
        const file=before ? path.join(root,'storage/framework/testing/seekers-layout.html') : path.join(output,url.searchParams.get('view')==='archive'?'archive.html':'active.html');
        return route.fulfill({contentType:'text/html',body:await fs.readFile(file,'utf8')});
    }
    const asset=path.resolve(root,'public','.'+decodeURIComponent(url.pathname));
    if(asset.startsWith(path.join(root,'public')+path.sep)) {
        try {return route.fulfill({body:await fs.readFile(asset),contentType:asset.endsWith('.css')?'text/css':asset.endsWith('.js')?'text/javascript':asset.endsWith('.svg')?'image/svg+xml':asset.endsWith('.png')?'image/png':'application/octet-stream'});} catch {}
    }
    return route.fulfill({status:404,body:''});
});
try {
    try { await fs.access(path.join(root,'storage/framework/testing/seekers-layout.html')); before=true; } catch {}
    await page.goto('http://127.0.0.1:8000/admin/seekers');
    if (before) await page.screenshot({path:path.join(output,'before-desktop.png'),fullPage:true});
    before=false;
    await page.reload();
    await page.addScriptTag({path:path.join(root,'node_modules/axe-core/axe.min.js')});
    const activeAxe=await page.evaluate(async()=>await axe.run('.seekers-page',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));
    expect(activeAxe.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}))).toEqual([]);
    await expect(page.getByRole('link',{name:'Active (514)',exact:true})).toBeVisible();
    await expect(page.locator('#admin-seekers-table')).not.toContainText('Aaron Alcantara');
    await page.screenshot({path:path.join(output,'active-desktop.png'),fullPage:true});
    const actions=page.locator('[data-menu-button]');
    expect(await actions.count()).toBe(10);
    await actions.nth(0).click();
    const archiveMenu=page.getByRole('menuitem',{name:'Archive',exact:true});
    await expect(archiveMenu).toBeVisible();
    await archiveMenu.click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('button',{name:'Cancel',exact:true}).click();
    await expect(page.getByRole('dialog')).not.toBeVisible();
    const all=page.getByRole('checkbox',{name:'Select all seekers on this page',exact:true});
    await all.check();
    await expect(page.locator('[data-archive-selected]')).toHaveText('10 selected');
    await page.getByRole('button',{name:'Archive selected',exact:true}).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await expect(page.locator('#archive-note')).toHaveAttribute('required','');
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).not.toBeVisible();
    await page.getByRole('link',{name:'Archived (8)',exact:true}).click();
    await expect(page.locator('.archive-table')).toContainText('Aaron Alcantara');
    await expect(page.locator('.archive-table select')).toHaveCount(0);
    await page.screenshot({path:path.join(output,'archive-desktop.png'),fullPage:true});
    const aaron=page.getByRole('row').filter({hasText:'Aaron Alcantara'});
    await aaron.getByRole('button',{name:'Restore',exact:true}).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await expect(page.getByLabel('Keep final status, stay active')).toBeVisible();
    await page.screenshot({path:path.join(output,'restore-dialog.png'),fullPage:false});
    await page.addScriptTag({path:path.join(root,'node_modules/axe-core/axe.min.js')});
    const dialogAxe=await page.evaluate(async()=>await axe.run('#archive-dialog',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));
    expect(dialogAxe.violations.map(v=>v.id)).toEqual([]);
    await page.keyboard.press('Escape');
    await expect(aaron.getByRole('button',{name:'Restore',exact:true})).toBeFocused();
    for (const width of [1024,768,390]) {
        await page.setViewportSize({width,height:900});
        await page.screenshot({path:path.join(output,'archive-'+width+'.png'),fullPage:true});
        const sizes=await page.evaluate(()=>({viewport:innerWidth,document:document.documentElement.scrollWidth}));
        expect(sizes.document).toBeLessThanOrEqual(sizes.viewport+1);
    }
    await page.addScriptTag({path:path.join(root,'node_modules/axe-core/axe.min.js')});
    const axe=await page.evaluate(async()=>await axe.run('.seekers-page',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));
    await fs.writeFile(path.join(output,'accessibility.json'),JSON.stringify(axe.violations,null,2));
    expect(axe.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}))).toEqual([]);
    expect(errors).toEqual([]);
    console.log('PASS: active/archive separation, selection, dialog, Escape/focus return, 1440/1024/768/390 layouts, WCAG AA scan; rendered read-only HTML.');
} finally { await browser.close(); }
