import * as cheerio from 'cheerio';
import { writeFile, mkdir } from 'fs/promises';
import { execSync } from 'child_process';

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

function fetchHtml(url) {
    const out = execSync(`curl -sk -L --max-time 30 "${url}"`, { encoding: 'utf-8', maxBuffer: 50 * 1024 * 1024 });
    return out;
}

function extract($, locale, url) {
    function txt(sel) {
        return $(sel).map((i, e) => $(e).text().trim().replace(/\s+/g, ' ')).get().filter(Boolean);
    }
    const headings = $('h1,h2,h3,h4,h5').map((i, e) => ({ tag: e.tagName.toUpperCase(), text: $(e).text().trim().replace(/\s+/g,' ') })).get().filter(h => h.text);
    const links = $('a').map((i, e) => ({ href: $(e).attr('href') || '', text: $(e).text().trim().replace(/\s+/g, ' ') })).get().filter(l => l.text);
    const buttons = $('button, .btn, [type=submit]').map((i, e) => ($(e).text() || $(e).attr('value') || '').trim().replace(/\s+/g,' ')).get().filter(Boolean);
    const labels = $('label').map((i, e) => $(e).text().trim().replace(/\s+/g,' ')).get().filter(Boolean);
    const placeholders = $('input[placeholder], textarea[placeholder]').map((i, e) => $(e).attr('placeholder')).get().filter(Boolean);
    const ariaLabels = $('[aria-label]').map((i, e) => $(e).attr('aria-label')).get().filter(Boolean);
    const altTexts = $('img[alt]').map((i, e) => $(e).attr('alt')).get().filter(Boolean);
    const title = $('title').text().trim();
    const metaDesc = $('meta[name=description]').attr('content') || '';
    const metaOgTitle = $('meta[property="og:title"]').attr('content') || '';
    const metaOgDesc = $('meta[property="og:description"]').attr('content') || '';
    const htmlLang = $('html').attr('lang') || '';
    // Visible body text (skip scripts/styles)
    $('script, style, noscript').remove();
    const bodyText = $('body').text().replace(/\s+/g, ' ').trim();
    return {
        url, title, metaDesc, metaOgTitle, metaOgDesc, html_lang: htmlLang,
        headings, links: links.slice(0, 500), buttons, labels, placeholders, ariaLabels, altTexts,
        bodyText: bodyText.slice(0, 200000),
    };
}

const out = { en: {}, de: {} };
for (const path of PAGES) {
    for (const loc of ['en', 'de']) {
        const sep = path.includes('?') ? '&' : '?';
        const url = `${BASE}${path}${sep}lang=${loc}`;
        console.log(`fetching ${loc} ${path}`);
        try {
            const html = fetchHtml(url);
            const $ = cheerio.load(html);
            out[loc][path] = extract($, loc, url);
            const data = out[loc][path];
            console.log(`  -> title="${data.title.slice(0,60)}" lang=${data.html_lang} bodyLen=${data.bodyText.length} headings=${data.headings.length}`);
        } catch (e) {
            out[loc][path] = { url, error: String(e) };
            console.log(`  -> ERROR ${e}`);
        }
    }
}

await mkdir('/home/user/mori-capital-2/scratch/audit-2026-06-26', { recursive: true });
await writeFile('/home/user/mori-capital-2/scratch/audit-2026-06-26/crawl.json', JSON.stringify(out, null, 2));
console.log('done');
