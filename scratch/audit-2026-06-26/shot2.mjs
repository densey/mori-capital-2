import { chromium } from 'playwright';

const file = process.argv[2];
const outClosed = process.argv[3];
const outOpen = process.argv[4];

const browser = await chromium.launch({
    headless: true,
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const page = await browser.newPage({ viewport: { width: 390, height: 780 }, deviceScaleFactor: 2, isMobile: true });
await page.goto('file://' + file, { waitUntil: 'load' });
await page.waitForTimeout(300);
await page.screenshot({ path: outClosed });
// open the drawer
await page.click('#aMenuToggle');
await page.waitForTimeout(500);
await page.screenshot({ path: outOpen });
await browser.close();
console.log('shots saved');
