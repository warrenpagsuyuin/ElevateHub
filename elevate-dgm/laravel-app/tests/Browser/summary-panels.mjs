import { chromium, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const phase = process.argv.includes('--before') ? 'before' : 'after';
const output = path.join(root, 'storage/framework/testing/summary-panels');
const browser = await chromium.launch({ channel: 'msedge', headless: true });
const page = await browser.newPage({ deviceScaleFactor: 1 });
const errors = [];
const measurements = [];
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.pathname === '/satellites/pasig/dashboard') return route.fulfill({ contentType: 'text/html', body: await fs.readFile(path.join(output, 'dashboard.html')) });
    const asset = path.resolve(root, 'public', '.' + decodeURIComponent(url.pathname));
    if (asset.startsWith(path.join(root, 'public') + path.sep)) {
        try { return route.fulfill({ body: await fs.readFile(asset), contentType: asset.endsWith('.css') ? 'text/css' : asset.endsWith('.js') ? 'text/javascript' : asset.endsWith('.png') ? 'image/png' : 'application/octet-stream' }); } catch {}
    }
    return route.fulfill({ status: 204, body: '' });
});
try {
    for (const width of [1440, 768, 375]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('http://127.0.0.1:8000/satellites/pasig/dashboard');
        await page.evaluate(() => document.fonts.ready);
        const values = await page.evaluate(() => {
            const rect = node => { const r = node.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom }; };
            const style = node => { const s = getComputedStyle(node); return { fontSize: s.fontSize, fontFamily: s.fontFamily, fontWeight: s.fontWeight, background: s.backgroundColor, color: s.color, minHeight: s.minHeight, padding: s.padding, gap: s.gap }; };
            return {
                width: innerWidth, scrollWidth: document.documentElement.scrollWidth,
                panels: [...document.querySelectorAll('.dashboard-panels .summary-panel')].map(panel => ({
                    bounds: rect(panel), title: style(panel.querySelector('h2,h3')), titleBounds: rect(panel.querySelector('h2,h3')),
                    body: style(panel.querySelector('dl')),
                    rows: [...panel.querySelectorAll('dl > div')].map(row => ({ bounds: rect(row), style: style(row), label: style(row.querySelector('dt')), value: style(row.querySelector('dd')), labelBounds: rect(row.querySelector('dt')), valueBounds: rect(row.querySelector('dd')), text: row.querySelector('dd').textContent.trim(), valueScroll: row.querySelector('dd').scrollWidth })),
                })),
            };
        });
        measurements.push(values);
        await page.screenshot({ path: path.join(output, `${phase}-${width}.png`), fullPage: true });
        if (phase === 'before') continue;
        await expect(page.locator('[data-period-form] input[name=month]')).toHaveValue('Live');
        await expect(page.locator('[data-year-control]')).toBeHidden();
        await expect(page.locator('.recent-activity')).toHaveCount(0);
        await expect(page.locator('.dashboard-panels .summary-panel > h3')).toHaveCount(2);
        expect(values.scrollWidth, `horizontal overflow at ${width}`).toBeLessThanOrEqual(width);
        for (const panel of values.panels) {
            expect(parseFloat(panel.title.fontSize)).toBeGreaterThanOrEqual(20);
            expect(panel.titleBounds.height).toBeGreaterThanOrEqual(52);
            expect(panel.titleBounds.height).toBeLessThanOrEqual(60);
            expect(panel.title.background).toBe('rgb(26, 26, 26)');
            const heights = panel.rows.map(row => row.bounds.height);
            expect(Math.max(...heights) - Math.min(...heights)).toBeLessThanOrEqual(1);
            for (const row of panel.rows) {
                expect(parseFloat(row.style.minHeight)).toBeGreaterThanOrEqual(64);
                expect(row.style.background).toBe('rgb(243, 244, 246)');
                expect(row.value.color).toBe('rgb(17, 17, 17)');
                expect(parseFloat(row.value.fontSize)).toBeGreaterThanOrEqual(24);
                expect(parseFloat(row.value.fontSize)).toBeLessThanOrEqual(28);
                expect(row.labelBounds.right).toBeLessThanOrEqual(row.valueBounds.x);
                expect(row.valueScroll).toBeLessThanOrEqual(Math.ceil(row.valueBounds.width));
                expect(row.valueBounds.right).toBeLessThanOrEqual(row.bounds.right);
            }
        }
        const before = JSON.parse(await fs.readFile(path.join(output, 'before.json'), 'utf8')).find(value => value.width === width);
        for (let i = 0; i < values.panels.length; i++) {
            expect(values.panels[i].title.fontFamily).toBe(before.panels[i].title.fontFamily);
            expect(values.panels[i].title.fontWeight).toBe(before.panels[i].title.fontWeight);
            for (let j = 0; j < values.panels[i].rows.length; j++) {
                expect(values.panels[i].rows[j].text).toBe(before.panels[i].rows[j].text);
                for (const field of ['label', 'value']) {
                    expect(values.panels[i].rows[j][field].fontFamily).toBe(before.panels[i].rows[j][field].fontFamily);
                    expect(values.panels[i].rows[j][field].fontWeight).toBe(before.panels[i].rows[j][field].fontWeight);
                }
            }
        }
        if (width === 375) expect(values.panels[0].rows[3].labelBounds.height).toBeGreaterThan(20);
        await page.getByRole('button', { name: 'Activity history', exact: true }).click();
        await expect(page.locator('#activity-history')).toBeVisible();
        expect(await page.locator('.history-entry').evaluate(node => getComputedStyle(node).backgroundColor)).toBe('rgb(243, 244, 246)');
        expect(await page.locator('.history-entry .activity-status').evaluate(node => getComputedStyle(node).color)).toBe('rgb(17, 17, 17)');
        await page.keyboard.press('Escape');
        await expect(page.getByRole('button', { name: 'Activity history', exact: true })).toBeFocused();
    }
    expect(errors).toEqual([]);
} finally {
    await fs.writeFile(path.join(output, `${phase}.json`), JSON.stringify(measurements, null, 2));
    await browser.close();
}
console.log(`${phase}: measured ${measurements.length} viewport sizes; ${errors.length} console/page errors.`);
