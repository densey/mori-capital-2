import { readFile, writeFile } from 'fs/promises';

const data = JSON.parse(await readFile('/home/user/mori-capital-2/scratch/audit-2026-06-26/crawl.json', 'utf-8'));

// English words that almost never appear in fluent German content
const EN_MARKERS = [
    // function words
    /\bthe\b/i, /\band\b/i, /\bof\b/i, /\bto\b/i, /\bwith\b/i, /\bfor\b/i, /\bfrom\b/i,
    /\bthis\b/i, /\bthat\b/i, /\bthese\b/i, /\bthose\b/i, /\bour\b/i, /\byour\b/i,
    /\bwe\b/i, /\bare\b/i, /\bwas\b/i, /\bwere\b/i, /\bbeen\b/i, /\bbeing\b/i,
    /\bmore\b/i, /\bmost\b/i, /\bless\b/i, /\bsome\b/i, /\bmany\b/i, /\bother\b/i,
    /\bwhich\b/i, /\bwhere\b/i, /\bwhen\b/i, /\bhow\b/i, /\bwhy\b/i,
    /\babout\b/i, /\bagainst\b/i, /\bbefore\b/i, /\bafter\b/i, /\bduring\b/i,
    /\bagainst\b/i, /\bbetween\b/i, /\bwithin\b/i, /\bwithout\b/i,
    /\bread\s+more\b/i, /\bview\s+all\b/i, /\blearn\s+more\b/i,
    /\bsubscribe\b/i, /\bnewsletter\b/i, /\bcontact\s+us\b/i,
    /\binsights?\b/i, /\bperformance\b/i, /\bdocuments?\b/i, /\bannouncements?\b/i,
    /\bdownload\b/i, /\boverview\b/i, /\bteam\b/i,
    /\bpolicies\b/i, /\bsuspension\b/i, /\bupdates?\b/i,
    /\bquarter(ly)?\b/i, /\bmonth(ly)?\b/i, /\bannual\b/i,
    /\bmanaging\b/i, /\bmanagement\b/i,
];

// Common German words: if a sentence has lots of these it's likely already German
const DE_MARKERS = [
    /\bder\b/i, /\bdie\b/i, /\bdas\b/i, /\bund\b/i, /\bvon\b/i, /\bmit\b/i,
    /\bnicht\b/i, /\bauf\b/i, /\bzu\b/i, /\beine?\b/i, /\bdurch\b/i,
    /\büber\b/i, /\bunser/i, /\bwir\b/i, /\bsind\b/i, /\bauch\b/i,
    /\bwerden\b/i, /\bhaben\b/i, /\bsein\b/i, /\bnach\b/i, /\bvor\b/i,
    /\bsich\b/i, /\bdem\b/i, /\bden\b/i, /\bdes\b/i, /\bbei\b/i,
];

function score(text) {
    let en = 0, de = 0;
    for (const r of EN_MARKERS) if (r.test(text)) en++;
    for (const r of DE_MARKERS) if (r.test(text)) de++;
    return { en, de };
}

function sentencesOf(text) {
    return text.split(/(?<=[.!?])\s+|\n+/).map(s => s.trim()).filter(s => s.length > 8 && s.length < 600);
}

// Known acceptable English-on-DE items (brand, proper nouns, regulatory abbreviations)
const ALLOW = [
    /^Mori\b/i,
    /^[A-Z]{2,5}\b/,                          // pure acronyms
    /^EUR|USD|GBP|TRY|CHF/i,
    /^Class\s+[A-Z]/i,                        // share class labels
    /^ISIN/i, /MFSA|UCITS|ESMA|MiFID|MFSA|FATCA|CRS/i,
    /^Q[1-4]\s*\d{4}/,
];

function isAllowed(s) {
    return ALLOW.some(r => r.test(s));
}

const findings = {};
const allowedLinks = new Set();
for (const [path, dePage] of Object.entries(data.de)) {
    const flags = [];
    const sentences = sentencesOf(dePage.bodyText || '');
    for (const s of sentences) {
        if (isAllowed(s)) continue;
        const sc = score(s);
        // If many EN markers and few DE markers => suspicious
        if (sc.en >= 3 && sc.de === 0) {
            flags.push({ kind: 'sentence', en_markers: sc.en, de_markers: sc.de, text: s });
        } else if (sc.en >= 2 && sc.de === 0 && /[A-Z][a-z]+\s+[A-Z][a-z]+/.test(s)) {
            flags.push({ kind: 'sentence_likely', en_markers: sc.en, de_markers: sc.de, text: s });
        }
    }
    // Check headings
    for (const h of dePage.headings || []) {
        if (isAllowed(h.text)) continue;
        const sc = score(h.text);
        if (sc.en >= 1 && sc.de === 0 && h.text.length > 4 && !/^\d+/.test(h.text)) {
            flags.push({ kind: 'heading', tag: h.tag, en_markers: sc.en, text: h.text });
        }
    }
    // Check buttons/labels
    for (const b of dePage.buttons || []) {
        if (isAllowed(b)) continue;
        const sc = score(b);
        if (sc.en >= 1 && sc.de === 0 && b.length > 3) {
            flags.push({ kind: 'button', en_markers: sc.en, text: b });
        }
    }
    for (const l of dePage.labels || []) {
        if (isAllowed(l)) continue;
        const sc = score(l);
        if (sc.en >= 1 && sc.de === 0 && l.length > 3) {
            flags.push({ kind: 'label', en_markers: sc.en, text: l });
        }
    }
    for (const p of dePage.placeholders || []) {
        if (isAllowed(p)) continue;
        const sc = score(p);
        if (sc.en >= 1 && sc.de === 0) {
            flags.push({ kind: 'placeholder', en_markers: sc.en, text: p });
        }
    }
    // Check link text
    for (const lnk of dePage.links || []) {
        if (isAllowed(lnk.text)) continue;
        if (allowedLinks.has(lnk.text)) continue;
        const sc = score(lnk.text);
        if (sc.en >= 1 && sc.de === 0 && lnk.text.length > 3 && lnk.text.length < 100 && !/\d{4}/.test(lnk.text)) {
            flags.push({ kind: 'link', en_markers: sc.en, text: lnk.text, href: lnk.href });
        }
    }
    if (flags.length) findings[path] = flags;
}

await writeFile('/home/user/mori-capital-2/scratch/audit-2026-06-26/findings.json', JSON.stringify(findings, null, 2));

// Dedupe summary
const allTexts = new Set();
for (const flags of Object.values(findings)) {
    for (const f of flags) allTexts.add(`${f.kind}|${f.text}`);
}
console.log(`\nUnique suspicious strings: ${allTexts.size}`);
console.log(`\nTop 100 distinct EN-leak candidates:`);
const sorted = [...allTexts].sort().slice(0, 100);
for (const t of sorted) console.log('  ' + t);
console.log(`\nFile-by-file count:`);
for (const [path, flags] of Object.entries(findings)) {
    console.log(`  ${path}: ${flags.length} flags`);
}
