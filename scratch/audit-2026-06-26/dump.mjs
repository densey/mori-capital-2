import { readFile, writeFile } from 'fs/promises';

const data = JSON.parse(await readFile('/home/user/mori-capital-2/scratch/audit-2026-06-26/crawl.json', 'utf-8'));

// For each DE page, dump headings, links, buttons, labels, alt texts, aria
function isSus(text) {
    if (!text) return false;
    if (/^[\d\s\-—:/,.()&%]+$/.test(text)) return false;
    // German-only stop words: if any present, likely DE
    if (/\b(der|die|das|und|von|mit|nicht|auf|für|zu|eine|einer|durch|über|unser|wir|sind|werden|haben|sich|dem|den|des|bei|als|nach|vor|aber|oder|weil|wenn|aus)\b/i.test(text)) return false;
    // English markers
    const en = /\b(the|and|of|with|for|from|this|that|our|your|we|are|was|were|been|being|more|less|some|many|other|which|where|when|how|why|about|against|before|after|during|between|within|without|read|view|learn|subscribe|newsletter|insights|performance|documents|announcements|download|overview|team|all|every|each|each|please|click|here|home|page|policies)\b/i;
    return en.test(text);
}

const buckets = {};
for (const [path, p] of Object.entries(data.de)) {
    if (p.error) continue;
    const items = [];
    if (isSus(p.title)) items.push({ kind: 'title', text: p.title });
    if (isSus(p.metaDesc)) items.push({ kind: 'metaDesc', text: p.metaDesc });
    if (isSus(p.metaOgTitle)) items.push({ kind: 'metaOgTitle', text: p.metaOgTitle });
    if (isSus(p.metaOgDesc)) items.push({ kind: 'metaOgDesc', text: p.metaOgDesc });
    for (const h of p.headings || []) {
        if (isSus(h.text)) items.push({ kind: `heading:${h.tag}`, text: h.text });
    }
    const linkSet = new Set();
    for (const l of p.links || []) {
        if (!linkSet.has(l.text) && isSus(l.text)) {
            items.push({ kind: 'link', text: l.text, href: l.href });
            linkSet.add(l.text);
        }
    }
    const btnSet = new Set();
    for (const b of p.buttons || []) {
        if (!btnSet.has(b) && isSus(b)) {
            items.push({ kind: 'button', text: b });
            btnSet.add(b);
        }
    }
    const altSet = new Set();
    for (const a of p.altTexts || []) {
        if (!altSet.has(a) && isSus(a)) {
            items.push({ kind: 'alt', text: a });
            altSet.add(a);
        }
    }
    const ariaSet = new Set();
    for (const a of p.ariaLabels || []) {
        if (!ariaSet.has(a) && isSus(a)) {
            items.push({ kind: 'aria', text: a });
            ariaSet.add(a);
        }
    }
    const phSet = new Set();
    for (const a of p.placeholders || []) {
        if (!phSet.has(a) && isSus(a)) {
            items.push({ kind: 'placeholder', text: a });
            phSet.add(a);
        }
    }
    if (items.length) buckets[path] = items;
}

for (const [path, items] of Object.entries(buckets)) {
    console.log(`\n=== DE ${path} (${items.length} suspicious) ===`);
    for (const i of items) {
        console.log(`  [${i.kind}] ${i.text}${i.href ? ' -> ' + i.href : ''}`);
    }
}

await writeFile('/home/user/mori-capital-2/scratch/audit-2026-06-26/de-suspects.json', JSON.stringify(buckets, null, 2));
console.log(`\n\nTotal pages with suspects: ${Object.keys(buckets).length}`);
