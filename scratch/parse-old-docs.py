#!/usr/bin/env python3
"""Parse Mori Capital old-site documents for both EN and DE pages.
Outputs JSON with explicit locale ('en' / 'de') so the importer can
tag each row correctly."""
from html.parser import HTMLParser
import json, html, re

class DocTableParser(HTMLParser):
    def __init__(self, locale, base_path=''):
        super().__init__()
        self.docs = []
        self.locale = locale
        self.base_path = base_path   # 'de/' prefix strip if DE page uses ../uploads
        self.cur_section = None
        self.in_h = False
        self.cur_h_level = None
        self.cur_h_text = []
        self.in_tr = False
        self.cur_row_tds = []
        self.in_td = False
        self.cur_td_text = []
        self.cur_td_href = None
        self.depth_in_td_a = 0

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag in ('h1', 'h2', 'h3', 'h4', 'h5', 'h6'):
            self.in_h = True
            self.cur_h_level = int(tag[1])
            self.cur_h_text = []
        elif tag == 'tr':
            self.in_tr = True
            self.cur_row_tds = []
        elif tag == 'td' and self.in_tr:
            self.in_td = True
            self.cur_td_text = []
            self.cur_td_href = None
            self.depth_in_td_a = 0
        elif tag == 'a' and self.in_td:
            self.depth_in_td_a += 1
            href = attrs.get('href', '')
            if href.endswith('.pdf') or 'uploads/' in href:
                # Normalise: strip leading ../ and de/ to get canonical uploads/...
                normalised = href.lstrip('./').lstrip('/')
                if normalised.startswith('de/'):
                    normalised = normalised[3:]
                self.cur_td_href = normalised

    def handle_endtag(self, tag):
        if tag in ('h1','h2','h3','h4','h5','h6'):
            t = ' '.join(self.cur_h_text).strip()
            if t and self.cur_h_level <= 3:
                self.cur_section = t
            self.in_h = False
        elif tag == 'tr' and self.in_tr:
            if len(self.cur_row_tds) >= 2:
                title = self.cur_row_tds[0]['text'].strip()
                href = next((td['href'] for td in self.cur_row_tds[1:] if td['href']), None)
                if title and href and len(title) > 3 and title.upper() != 'PDF':
                    title = re.sub(r'\s+', ' ', html.unescape(title)).strip()
                    self.docs.append({
                        'title':   title,
                        'href':    href,
                        'section': self.cur_section,
                        'locale':  self.locale,
                    })
            self.in_tr = False
        elif tag == 'td' and self.in_td:
            self.cur_row_tds.append({
                'text': ' '.join(self.cur_td_text).strip(),
                'href': self.cur_td_href,
            })
            self.in_td = False
        elif tag == 'a' and self.in_td:
            self.depth_in_td_a -= 1

    def handle_data(self, data):
        if self.in_h:
            self.cur_h_text.append(data.strip())
        if self.in_td and self.depth_in_td_a == 0:
            self.cur_td_text.append(data.strip())


def parse_page(path, locale):
    p = DocTableParser(locale)
    with open(path) as f:
        p.feed(f.read())
    return p.docs


# === English pages (already parsed earlier — re-parse for consistency) ===
en_other  = parse_page('/tmp/old-other.html',   'en')
en_update = parse_page('/tmp/old-updates.html', 'en')

# === German pages (new) ===
de_other  = parse_page('/tmp/de-other.html',   'de')
de_update = parse_page('/tmp/de-updates.html', 'de')

# Save combined files
all_other  = en_other  + de_other
all_update = en_update + de_update

with open('/home/user/mori-capital-2/scratch/other-docs.json', 'w') as f:
    json.dump(all_other, f, indent=2, ensure_ascii=False)
with open('/home/user/mori-capital-2/scratch/update-docs.json', 'w') as f:
    json.dump(all_update, f, indent=2, ensure_ascii=False)

print(f"=== OTHER DOCS ===")
print(f"  EN: {len(en_other)}")
print(f"  DE: {len(de_other)}")
print(f"  total: {len(all_other)}")
print()
print(f"=== UPDATES DURING SUSPENSION ===")
print(f"  EN: {len(en_update)}")
print(f"  DE: {len(de_update)}")
print(f"  total: {len(all_update)}")
print()
print(f"=== Sample DE updates ===")
for d in de_update[:8]:
    print(f"  - {d['title'][:70]}  →  {d['href'][:50]}")
print()
print(f"=== Sample DE other ===")
for d in de_other[:8]:
    print(f"  - {d['title'][:70]}  →  {d['href'][:50]}")
