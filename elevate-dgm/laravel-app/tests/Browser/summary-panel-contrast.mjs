import fs from 'node:fs/promises';

const css = await fs.readFile('resources/css/shared-theme.css', 'utf8');
const token = name => {
    const match = css.match(new RegExp(`--${name}:\\s*(#[a-f\\d]{6})`, 'i'));
    if (!match) throw new Error(`Missing literal color token ${name}`);
    return match[1];
};
const luminance = hex => {
    const channels = hex.slice(1).match(/../g).map(value => parseInt(value, 16) / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
    return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722;
};
for (const [foreground, background] of [['summary-row-label', 'summary-row-bg'], ['summary-row-value', 'summary-row-bg'], ['panel-title-text', 'panel-title-bg']]) {
    const values = [luminance(token(foreground)), luminance(token(background))].sort((a, b) => b - a);
    const ratio = (values[0] + .05) / (values[1] + .05);
    console.log(`${foreground}: ${ratio.toFixed(2)}:1`);
    if (ratio < 4.5) throw new Error(`${foreground} fails AA`);
}
