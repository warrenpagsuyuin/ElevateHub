import { chromium, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
const root = process.cwd();
const output = path.join(root, 'storage/framework/testing/otp-mail');
const browser = await chromium.launch({ executablePath: process.env.EMAIL_BROWSER_PATH || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
const errors = [];
try {
 for (const width of [600, 375]) {
  for (const colorScheme of ['light', 'dark']) {
   const page = await browser.newPage({viewport:{width,height:1000},colorScheme});
   page.on('pageerror', error => errors.push(error.message));
   await page.route('**/*', async route => {
    if (new URL(route.request().url()).pathname === '/images/email/elevate-logo.png') return route.fulfill({contentType:'image/png',body:await fs.readFile(path.join(root,'public/images/email/elevate-logo.png'))});
    return route.fulfill({contentType:'text/html',body:await fs.readFile(path.join(output,'preview.html'))});
   });
   await page.goto('http://email-preview.test/');
   await expect(page.locator('img')).toHaveJSProperty('naturalWidth',300);
   expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBe(width);
   await expect(page.locator('.mail-body')).toHaveCSS('background-color',colorScheme==='dark'?'rgb(26, 26, 26)':'rgb(255, 255, 255)');
   const digits = page.locator('.code-digits');
   await digits.dblclick();
   expect(await page.evaluate(()=>window.getSelection().toString())).toBe('123456');
   await page.evaluate(()=>window.getSelection().removeAllRanges());
   await page.screenshot({path:path.join(output,width+'-'+colorScheme+'.png'),fullPage:true});
   await page.close();
  }
 }
 expect(errors).toEqual([]);
 console.log('Four Edge previews pass: no overflow, logo loaded, light/dark colors, double-click copies six digits, no page errors.');
} finally { await browser.close(); }
