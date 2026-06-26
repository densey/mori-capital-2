import { chromium } from 'playwright';
import { writeFile, mkdir } from 'fs/promises';
import { dirname } from 'path';

const BASE = 'https://mori.vylence.com';

const PAGES = [
    '/',
    '/about.php',
    '/investment-style.php',
    '/team.php',
    '/insights.php',
    '/contact.php',
    '/fund-eastern-european.php',
    '/fund-ottoman.php',
    '/fund-performance.php?fund=eastern-european',
    '/fund-performance.php?fund=ottoman',
    '/documents.php',
    '/company-policies.php',
    '/other-documents.php',
    '/updates-during-suspension.php',
    '/announcements.php',
    '/legal.php',
    '/privacy.php',
    '/cookies.php',
];

async function writeJson(path, data) {
    await mkdir(dirname(path), { recursive: true });
    await writeFile(path, JSON.stringify(data, null, 2));
}

async function dumpPage(context, path, locale) {
    const page = await context.newPage();
    const sep = path.includes('?') ? '&' : '?';
    const url = `${BASE}${path}${sep}lang=${locale}`;
    try {
        await page.goto(url, { waitUntil: 'load', timeout: 30000 });
        await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(()=>{});
        await page.waitForTimeout(500);
    } catch (e) {
        await page.close();
        return { url, error: String(e) };
    }
    let data;
    try {
        data = await page.evaluate(() => {
            function getText(el) {
                return ((el?.textContent || '')).replace(/\s+/g,' ').trim();
            }
            const headings = Array.from(document.querySelectorAll('h1,h2,h3,h4,h5')).map(h => ({ tag: h.tagName, text: getText(h) })).filter(h => h.text);
            const links = Array.from(document.querySelectorAll('a')).map(a => ({ href: a.href, text: getText(a) })).filter(l => l.text);
            const buttons = Array.from(document.querySelectorAll('button, .btn, [type=submit]')).map(b => getText(b) || b.value).filter(Boolean);
            const labels = Array.from(document.querySelectorAll('label')).map(l => getText(l)).filter(Boolean);
            const placeholders = Array.from(document.querySelectorAll('input[placeholder], textarea[placeholder]')).map(i => i.placeholder).filter(Boolean);
            const ariaLabels = Array.from(document.querySelectorAll('[aria-label]')).map(i => i.getAttribute('aria-label')).filter(Boolean);
            const altTexts = Array.from(document.querySelectorAll('img[alt]')).map(i => i.alt).filter(Boolean);
            return {
                title: document.title,
                metaDesc: document.querySelector('meta[name=description]')?.content || '',
                metaOgTitle: document.querySelector('meta[property=\"og:title\"]')?.content || '',
                metaOgDesc: document.querySelector('meta[property=\"og:description\"]')?.content || '',
                html_lang: document.documentElement.lang,
                headings,
                links: links.slice(0, 300),
                buttons,
                labels,
                placeholders,
                ariaLabels,
                altTexts,
                bodyText: (document.body.textContent || '').replace(/\s+/g,' ').trim().slice(0, 80000),
            };
        });
    } catch (e) {
        data = { error: String(e) };
    }
    await page.close();
    return { url, ...data };
}

(async () => {
    const browser = await chromium.launch({
        headless: true,
        executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
        proxy: { server: process.env.HTTPS_PROXY || 'http://127.0.0.1:38031' },
        args: [
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--ignore-certificate-errors',
            '--disable-features=NetworkService,NetworkServiceInProcess,SafeBrowsing',
        ],
    });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        ignoreHTTPSErrors: true,
        userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    });
    const out = { en: {}, de: {} };
    for (const path of PAGES) {
        for (const loc of ['en', 'de']) {
            console.log(`fetching ${loc} ${path}`);
            const data = await dumpPage(context, path, loc);
            out[loc][path] = data;
            console.log(`  -> title="${data.title}" lang=${data.html_lang} bodyLen=${(data.bodyText||'').length} err=${data.error||''}`);
        }
    }
    await browser.close();
    await writeJson('/home/user/mori-capital-2/scratch/audit-2026-06-26/crawl.json', out);
    console.log('done');
})();
