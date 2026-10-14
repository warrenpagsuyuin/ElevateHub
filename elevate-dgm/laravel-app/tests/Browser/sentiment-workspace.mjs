import { chromium, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

// Run php tests/Browser/render-sentiment-workspace.php first.
const root = process.cwd();
const output = path.join(root, 'storage/framework/testing/sentiment-workspace');
const browser = await chromium.launch({ channel: 'msedge', headless: true });
const page = await browser.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    const section = url.pathname === '/admin/analytics/sentiment' ? 'overview'
        : url.pathname === '/admin/analytics/sentiment/research/review' ? 'review' : null;
    if (section) return route.fulfill({ contentType: 'text/html', body: await fs.readFile(path.join(output, `${section}.html`), 'utf8') });
    const asset = path.resolve(root, 'public', '.' + decodeURIComponent(url.pathname));
    if (asset.startsWith(path.join(root, 'public') + path.sep)) {
        try { return route.fulfill({ body: await fs.readFile(asset), contentType: asset.endsWith('.css') ? 'text/css' : asset.endsWith('.js') ? 'text/javascript' : asset.endsWith('.png') ? 'image/png' : 'application/octet-stream' }); } catch {}
    }
    return route.fulfill({ status: 204, body: '' });
});
try {
    for (const width of [320, 375, 768, 1024, 1280, 1536]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('http://127.0.0.1:8000/admin/analytics/sentiment');
        const tabs = page.getByRole('navigation', { name: 'Sentiment workspace' });
        await expect(tabs.getByRole('link')).toHaveText(['Overview', 'Human Feedback Review']);
        await expect(tabs.getByRole('link', { name: 'Overview', exact: true })).toHaveAttribute('aria-current', 'page');
        await expect(page.getByRole('link', { name: 'Export evaluation' })).toHaveAttribute('href', 'http://127.0.0.1:8000/admin/analytics/sentiment/research/export');
        await tabs.getByRole('link', { name: 'Human Feedback Review', exact: true }).click();
        await expect(tabs.getByRole('link', { name: 'Human Feedback Review', exact: true })).toHaveAttribute('aria-current', 'page');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        expect(await tabs.evaluate(el => getComputedStyle(el).overflowX)).toBe('auto');
        await expect(page.getByRole('button', { name: 'Import new feedback', exact: true })).toBeVisible();
        await page.screenshot({ path: path.join(output, `review-${width}.png`), fullPage: true });
        await tabs.getByRole('link', { name: 'Overview', exact: true }).click();
        await expect(page.getByText('Sentiment at a glance', { exact: true })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: path.join(output, `overview-${width}.png`), fullPage: true });
        console.log(`PASS ${width}px: two tabs, navigation, active state, overflow and export link`);
    }
    expect(errors).toEqual([]);
    console.log('PASS no console/page errors');
} finally {
    await browser.close();
}
