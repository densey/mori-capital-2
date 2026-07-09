import { chromium } from 'playwright';

const file = process.argv[2];
const out = process.argv[3];

const browser = await chromium.launch({
    headless: true,
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const page = await browser.newPage({ viewport: { width: 900, height: 800 }, deviceScaleFactor: 2 });
await page.goto('file://' + file, { waitUntil: 'load' });
await page.waitForTimeout(400);
await page.screenshot({ path: out });
await browser.close();
console.log('shot saved:', out);
