import { chromium, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
const root = process.cwd(), dir = path.join(root, 'storage/framework/testing/satellite-archive');
const browser = await chromium.launch({channel:'msedge', headless:true});
const page = await browser.newPage({viewport:{width:1440,height:850}});
let archived = false, failure = 0;
const errors = [];
page.on('pageerror', e => errors.push(e.message));
await page.route('**/*', async route => {
    const request = route.request(), url = new URL(request.url());
    if (request.method() === 'POST') {
        if (failure) return route.fulfill({status:failure, json:{message:'Failure'}});
        archived = !url.pathname.endsWith('/restore');
        return route.fulfill({json:archived ? {message:'Satellite archived',restore_url:'http://127.0.0.1:8000/admin/satellites/plp/restore'} : {message:'Satellite restored'}});
    }
    if (url.pathname === '/admin/satellites') return route.fulfill({contentType:'text/html', body:await fs.readFile(path.join(dir, url.searchParams.get('view') === 'archived' ? 'archived.html' : archived ? 'after.html' : 'active.html'),'utf8')});
    const asset = path.resolve(root,'public','.'+decodeURIComponent(url.pathname));
    if(asset.startsWith(path.join(root,'public')+path.sep)) try { return route.fulfill({body:await fs.readFile(asset), contentType:asset.endsWith('.js')?'text/javascript':asset.endsWith('.css')?'text/css':'application/octet-stream'}); } catch {}
    return route.fulfill({status:404,body:''});
});
try {
    await page.goto('http://127.0.0.1:8000/admin/satellites');
    const menu = page.getByRole('button',{name:'Actions for Plp',exact:true});
    await menu.scrollIntoViewIfNeeded(); await menu.click();
    const panel = page.locator('.data-table-row-menu.open .data-table-menu');
    await expect(panel).toBeVisible();
    const box = await panel.boundingBox();
    expect(box.y).toBeGreaterThanOrEqual(0); expect(box.y+box.height).toBeLessThanOrEqual(850);
    expect(await panel.evaluate(el => el.matches(':popover-open'))).toBe(true);
    await page.getByRole('menuitem',{name:'Archive satellite',exact:true}).last().click();
    await expect(page.locator('[data-table-confirm-message]')).toContainText('This satellite still has 1 members');
    await page.getByRole('button',{name:'Cancel',exact:true}).click();
    await expect(menu).toBeVisible();
    for (const status of [403,404,500]) {
        failure = status; await menu.click();
        await page.getByRole('menuitem',{name:'Archive satellite',exact:true}).last().click();
        await page.getByRole('button',{name:'Confirm',exact:true}).click();
        await expect(page.locator('#satellite-toast')).toBeVisible(); await expect(menu).toBeVisible();
    }
    failure = 0; await menu.click();
    await page.getByRole('menuitem',{name:'Archive satellite',exact:true}).last().click();
    await page.getByRole('button',{name:'Confirm',exact:true}).click();
    await expect(page.locator('#satellite-toast')).toContainText('Satellite archived');
    await expect(menu).toHaveCount(0);
    await expect(page.locator('.stats .value').first()).toHaveText('9');
    await page.getByRole('link',{name:'Undo',exact:true}).click();
    await expect(page.locator('#satellite-toast')).toContainText('Satellite restored');
    await expect(page.locator('.stats .value').first()).toHaveText('10');
    await page.goto('http://127.0.0.1:8000/admin/satellites?view=archived');
    await expect(page.getByText('Archived on',{exact:false})).toBeVisible();
    await page.getByRole('button',{name:'Actions for Plp',exact:true}).click();
    await expect(page.getByRole('menuitem',{name:'View details'})).toBeVisible();
    await page.getByRole('menuitem',{name:'Restore',exact:true}).click();
    await expect(page.locator('[data-table-confirm-message]')).toContainText('Restore Plp?');
    await page.getByRole('button',{name:'Cancel',exact:true}).click();
    expect(errors).toEqual([]);
    await page.screenshot({path:path.join(dir,'archived.png'),fullPage:true});
    console.log('PASS: menu top layer/viewport, warning, Cancel, 403/404/500 row retention, archive, updated cards, Undo, archived actions and restore confirmation.');
} finally { await browser.close(); }
