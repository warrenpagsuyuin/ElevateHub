import { chromium, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

// Run php tests/Browser/render-follow-ups.php first. No live requests or mutations.
const root = process.cwd();
const output = path.join(root, 'storage/framework/testing/follow-ups');
const browser = await chromium.launch({ channel: 'msedge', headless: true });
const page = await browser.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
let mode = 'readonly';
await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.pathname === '/satellites/pasig/follow-ups') {
        return route.fulfill({ contentType: 'text/html', body: await fs.readFile(path.join(output, `${mode}.html`), 'utf8') });
    }
    const asset = path.resolve(root, 'public', '.' + decodeURIComponent(url.pathname));
    if (asset.startsWith(path.join(root, 'public') + path.sep)) {
        try {
            return route.fulfill({ body: await fs.readFile(asset), contentType: asset.endsWith('.css') ? 'text/css' : asset.endsWith('.js') ? 'text/javascript' : asset.endsWith('.png') ? 'image/png' : 'application/octet-stream' });
        } catch {}
    }
    return route.fulfill({ status: 204, body: '' });
});
try {
    for (const width of [320, 375, 768, 1024, 1280, 1536]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('http://127.0.0.1:8000/satellites/pasig/follow-ups');
        await expect(page.getByText('Sentiment Trends', { exact: true })).toHaveCount(0);
        const geometry = await page.evaluate(() => {
            const stats = document.querySelector('.follow-up-stats');
            const filters = document.querySelector('.follow-up-filters');
            const buttons = [...document.querySelectorAll('.follow-up-filter-actions > *')];
            const selects = [...filters.querySelectorAll('select')];
            return {
                overflow: document.documentElement.scrollWidth > innerWidth,
                columns: getComputedStyle(stats).gridTemplateColumns.split(' ').length,
                filterWidth: filters.getBoundingClientRect().width,
                contentWidth: document.querySelector('.follow-up-history').getBoundingClientRect().width,
                buttons: buttons.map(el => ({ height: el.getBoundingClientRect().height, nowrap: getComputedStyle(el).whiteSpace })),
                bottoms: [...selects, ...buttons].map(el => el.getBoundingClientRect().bottom),
            };
        });
        expect(geometry.overflow, `page overflow at ${width}`).toBe(false);
        expect(geometry.columns).toBe(width >= 1024 ? 4 : width >= 480 ? 2 : 1);
        expect(geometry.filterWidth).toBeCloseTo(geometry.contentWidth, 0);
        for (const button of geometry.buttons) {
            expect(button.height).toBeGreaterThanOrEqual(44);
            expect(button.nowrap).toBe('nowrap');
        }
        if (width >= 1024) expect(Math.max(...geometry.bottoms) - Math.min(...geometry.bottoms)).toBeLessThan(2);
        if (width < 1200) {
            await page.getByRole('button', { name: 'Open navigation', exact: true }).click();
            await expect(page.locator('#app-sidebar')).toHaveAttribute('aria-hidden', 'false');
            await page.keyboard.press('Escape');
            await expect(page.locator('#app-sidebar')).toHaveAttribute('aria-hidden', 'true');
        }
        await page.getByText('Why this sentiment?', { exact: true }).click();
        await expect(page.locator('.follow-up-table details')).toHaveAttribute('open', '');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        if (width < 768) {
            const pinned = await page.locator('.table-scroll').evaluate(el => {
                el.scrollLeft = 240;
                return Math.abs(el.querySelector('tbody td').getBoundingClientRect().left - el.getBoundingClientRect().left) < 2;
            });
            expect(pinned).toBe(true);
        }
        await expect(page.locator('.follow-up-table thead th')).toHaveCount(6);
        await expect(page.getByRole('columnheader', { name: 'Actions' })).toHaveCount(0);
        await page.locator('.table-scroll').evaluate(el => { el.scrollLeft = 0; });
        await page.screenshot({ path: path.join(output, `${width}.png`), fullPage: true });
        await page.locator('.follow-up-filters select[name=status]').selectOption('Completed');
        await page.locator('.follow-up-filters select[name=method]').selectOption('Phone');
        await page.locator('.follow-up-filters select[name=sentiment]').selectOption('Positive');
        await page.getByRole('button', { name: 'Apply Filters', exact: true }).click();
        await expect(page).toHaveURL(/status=Completed&method=Phone&sentiment=Positive/);
        await page.getByRole('link', { name: 'Clear Filters', exact: true }).click();
        await expect(page).toHaveURL('http://127.0.0.1:8000/satellites/pasig/follow-ups');
        console.log(`PASS ${width}px: layout, drawer, details, sticky column, filter URLs`);
    }
    mode = 'manager';
    await page.setViewportSize({ width: 320, height: 900 });
    await page.reload();
    await expect(page.locator('.follow-up-table thead th')).toHaveCount(6);
    await expect(page.getByRole('columnheader', { name: 'Actions' })).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect(errors).toEqual([]);
    console.log('PASS manager table layout and no console/page errors');
} finally {
    await browser.close();
}
