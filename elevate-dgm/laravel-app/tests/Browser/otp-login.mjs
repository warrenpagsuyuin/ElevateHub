import {chromium, expect} from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
const root = process.cwd(), output = path.join(root, 'storage/framework/testing/otp-login');
const browser = await chromium.launch({channel: 'msedge', headless: true});
const page = await browser.newPage({viewport: {width: 1280, height: 1000}});
const errors = [];
page.on('pageerror', error => errors.push(error.message));
const base = Date.parse('2026-10-13T00:00:00Z');
const iso = seconds => new Date(base + seconds * 1000).toISOString();
let state = {server_now: iso(0), expires_at: iso(300), resend_available_at: iso(60), attempts_left: 5, locked_until: null, delivery_failed: false, masked_email: 'w*****0@example.com', resends_left: 3};
let verifies = 0, resends = 0;
await page.route('**/*', async route => {
    const request = route.request(), url = new URL(request.url());
    if (request.method() === 'POST') {
        if (url.pathname.includes('resend')) {
            resends++;
            state = {...state, server_now: iso(301), expires_at: iso(601), resend_available_at: iso(361), attempts_left: 5, locked_until: null, resends_left: 2};
            return route.fulfill({json: {...state, message: 'A new code has been requested. Check your email.'}});
        }
        verifies++;
        state = {...state, attempts_left: 4};
        return route.fulfill({status: 422, json: {...state, message: "That code isn't correct. 4 attempts left."}});
    }
    if (url.pathname.endsWith('/state')) return route.fulfill({json: state});
    if (url.pathname === '/') {
        let html = await fs.readFile(path.join(output, 'otp.html'), 'utf8');
        html = html.replace(/data-state="[^"]*"/, `data-state="${JSON.stringify(state).replaceAll('"', '&quot;')}"`);
        return route.fulfill({contentType: 'text/html', body: html});
    }
    const asset = path.resolve(root, 'public', '.' + decodeURIComponent(url.pathname));
    if (asset.startsWith(path.join(root, 'public') + path.sep)) {
        try { return route.fulfill({body: await fs.readFile(asset), contentType: asset.endsWith('.js') ? 'text/javascript' : asset.endsWith('.css') ? 'text/css' : 'image/png'}); } catch {}
    }
    return route.fulfill({status: 404, body: ''});
});
try {
    await page.clock.install({time: new Date(base)});
    await page.goto('http://127.0.0.1:8000/');
    const boxes = page.locator('.otp-digit');
    await expect(boxes).toHaveCount(6);
    await expect(boxes.first()).toBeFocused();
    await expect(page.locator('#otp-timer')).toHaveText('Code expires in 05:00');
    await expect(page.locator('#otp-resend')).toBeDisabled();
    await page.clock.setSystemTime(new Date(base + 86400000));
    await expect(page.locator('#otp-timer')).toHaveText('Code expires in 05:00');
    await boxes.first().pressSequentially('12');
    await expect(boxes.nth(2)).toBeFocused();
    await boxes.nth(2).press('Backspace');
    await expect(boxes.nth(1)).toBeFocused();
    await expect(boxes.nth(1)).toHaveValue('');
    await boxes.nth(1).press('ArrowLeft');
    await expect(boxes.first()).toBeFocused();
    await boxes.first().fill('abc');
    await expect(boxes.first()).toHaveValue('');
    await boxes.first().evaluate(input => {
        const clipboardData = new DataTransfer(); clipboardData.setData('text', '123456');
        input.dispatchEvent(new ClipboardEvent('paste', {bubbles: true, clipboardData}));
    });
    await expect(page.locator('#otp-feedback')).toContainText('4 attempts left');
    expect(verifies).toBe(1);
    await expect(boxes.first()).toBeFocused();
    await expect(boxes.first()).toHaveAttribute('aria-invalid', 'true');
    await boxes.first().fill('654321');
    await expect.poll(() => verifies).toBe(2);
    await expect(boxes.first()).toHaveValue('');
    await page.clock.runFor(241000);
    await expect(page.locator('#otp-timer')).toHaveAttribute('data-state', 'warning');
    await expect(page.locator('#otp-announcement')).toContainText('One minute');
    await page.clock.runFor(60000);
    await expect(page.locator('#otp-timer')).toContainText('Code expired');
    await expect(boxes.first()).toBeDisabled();
    await expect(page.locator('#otp-verify')).toBeDisabled();
    await expect(page.locator('#otp-resend')).toBeEnabled();
    await page.locator('#otp-resend').click();
    await expect(page.locator('#otp-timer')).toHaveText('Code expires in 05:00');
    expect(resends).toBe(1);
    await expect(boxes.first()).toBeEnabled();
    await expect(page.locator('#otp-resend')).toBeDisabled();
    state = {...state, attempts_left: 0, locked_until: iso(421)};
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow')));
    await expect(page.locator('#otp-lock')).toContainText('02:00');
    await expect(boxes.first()).toBeDisabled();
    state = {...state, attempts_left: 5, locked_until: null};
    await page.reload();
    await page.addScriptTag({path: path.join(root, 'node_modules/axe-core/axe.min.js')});
    const axeResults = await page.evaluate(() => axe.run(document, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa']}}));
    expect(axeResults.violations.map(item => ({id: item.id, nodes: item.nodes.map(node => node.target)}))).toEqual([]);
    for (const width of [1280, 390, 320]) {
        await page.setViewportSize({width, height: 900});
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        const bounds = await boxes.first().boundingBox();
        expect(bounds.width).toBeGreaterThanOrEqual(44);
        await page.screenshot({path: path.join(output, `otp-${width}.png`), fullPage: true});
    }
    await page.emulateMedia({reducedMotion: 'reduce'});
    expect(await page.locator('.otp-spinner').evaluate(el => getComputedStyle(el).animationName)).toBe('none');
    state = {...state, expires_at: iso(340), server_now: iso(330), resends_left: 0};
    await page.reload();
    await expect(page.locator('#otp-timer')).toHaveText('Code expires in 00:10');
    await expect(page.locator('#otp-resend')).toBeDisabled();
    await expect(page.locator('#otp-resend-limit')).toContainText('limit reached');
    expect(errors).toEqual([]);
    console.log('PASS: OTP server timer, device clock independence, typing, keyboard navigation, paste/autofill, automatic verification, errors, expiry, resend, lockout, refresh, reduced motion, accessibility and 320px layout. Requests mocked; no emails sent.');
} finally { await browser.close(); }
