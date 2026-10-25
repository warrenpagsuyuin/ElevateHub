import { chromium, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const browser = await chromium.launch({ channel: 'msedge', headless: true });
const page = await browser.newPage({ reducedMotion: 'reduce' });
const errors = [];
page.on('pageerror', error => errors.push(error.message));
await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.pathname === '/fixture') return route.fulfill({ contentType: 'text/html', body: await fs.readFile('storage/framework/testing/sentiment-result/results.html') });
    const asset = path.resolve(root, 'public', '.' + decodeURIComponent(url.pathname));
    if (asset.startsWith(path.join(root, 'public') + path.sep)) {
        try { return route.fulfill({ body: await fs.readFile(asset), contentType: asset.endsWith('.css') ? 'text/css' : 'application/octet-stream' }); } catch {}
    }
    return route.fulfill({ status: 204, body: '' });
});
try {
    for (const width of [1440, 375]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('http://127.0.0.1:8000/fixture');
        for (const sentiment of ['Neutral', 'Positive', 'Negative']) {
            const result = page.locator(`[data-sentiment="${sentiment}"]`);
            await expect(result.locator('.sentiment-result-explanation')).toBeHidden();
            await result.locator('.sentiment-result-badge').click();
            await expect(result.locator('.sentiment-result-explanation')).toBeVisible();
            await expect(result.locator('.sentiment-result-confidence')).toBeVisible();
            await expect(result.locator('.sentiment-result-confidence')).toHaveText('50% confidence');
            expect(await result.locator('svg').evaluate(node => getComputedStyle(node).transform)).toBe('matrix(-1, 0, 0, -1, 0, 0)');
            await result.locator('.sentiment-result-badge').click();
            await expect(result.locator('.sentiment-result-explanation')).toBeHidden();
        }
        const neutral = page.locator('[data-sentiment="Neutral"]');
        await neutral.locator('summary').focus();
        await page.keyboard.press('Enter');
        await expect(neutral.locator('.sentiment-result-explanation')).toBeVisible();
        await page.keyboard.press('Space');
        await expect(neutral.locator('.sentiment-result-explanation')).toBeHidden();
        for (const summary of await page.locator('.sentiment-result > summary').all()) await summary.click();
        await expect(page.locator('.sentiment-result[open]')).toHaveCount(3);
        const geometry = await page.locator('.sentiment-result-explanation').evaluateAll(nodes => nodes.map(node => { const r = node.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, right: r.right }; }));
        for (let i = 0; i < geometry.length; i++) {
            expect(geometry[i].right).toBeLessThanOrEqual(width);
            if (i) expect(geometry[i].top).toBeGreaterThanOrEqual(geometry[i - 1].bottom);
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        await expect(page.getByText('Why this sentiment?', { exact: true })).toHaveCount(0);
    }
    expect(errors).toEqual([]);
    console.log('PASS: Neutral/Positive/Negative click toggles, Enter/Space, confidence, chevrons, concurrent disclosures, desktop/mobile wrapping; no page errors.');
} finally { await browser.close(); }
