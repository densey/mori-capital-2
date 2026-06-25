#!/usr/bin/env python3
"""Re-parse Mori Capital live-site documents pages to extract correct titles."""
from html.parser import HTMLParser
import json, html, re

class FixedDocParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.docs = []
        self.cur_section = None
        # heading tracking
        self.in_h = False
        self.cur_h_level = None
        self.cur_h_text = []
        # row tracking
        self.in_tr = False
        self.cur_row_tds = []     # list of {text, href}
        self.in_td = False
        self.cur_td_text = []
        self.cur_td_href = None
        self.depth_in_td_a = 0    # nested <a> depth inside td

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
                self.cur_td_href = href

    def handle_endtag(self, tag):
        if tag in ('h1', 'h2', 'h3', 'h4', 'h5', 'h6'):
            t = ' '.join(self.cur_h_text).strip()
            if t and self.cur_h_level <= 3:
                self.cur_section = t
            self.in_h = False
        elif tag == 'tr' and self.in_tr:
            # Need at least 2 tds: title + link
            if len(self.cur_row_tds) >= 2:
                title = self.cur_row_tds[0]['text'].strip()
                href = next((td['href'] for td in self.cur_row_tds[1:] if td['href']), None)
                if title and href and len(title) > 3 and title.upper() != 'PDF':
                    # Strip extra whitespace + decode entities
                    title = re.sub(r'\s+', ' ', html.unescape(title)).strip()
                    self.docs.append({
                        'title': title,
                        'href': href,
                        'section': self.cur_section,
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
        if self.in_td:
            # Only collect text outside <a> tags (the PDF link text is "PDF" — skip)
            if self.depth_in_td_a == 0:
                self.cur_td_text.append(data.strip())

# Parse Other Documents
with open('/tmp/old-other.html') as f:
    p = FixedDocParser()
    p.feed(f.read())
other_docs = p.docs

# Parse Updates During Suspension
with open('/tmp/old-updates.html') as f:
    p = FixedDocParser()
    p.feed(f.read())
update_docs = p.docs

print(f"=== OTHER DOCS ({len(other_docs)}) ===")
sections = {}
for d in other_docs:
    sections.setdefault(d.get('section', '?'), []).append(d)
for sect in sorted(sections.keys(), reverse=True):
    print(f"\n[{sect}]")
    for d in sections[sect][:6]:
        print(f"  - {d['title'][:80]}")
    if len(sections[sect]) > 6:
        print(f"  ... +{len(sections[sect]) - 6} more")

print(f"\n=== UPDATE DOCS ({len(update_docs)}) ===")
for d in update_docs[:35]:
    print(f"  - {d['title'][:80]}")

with open('/home/user/mori-capital-2/scratch/other-docs.json', 'w') as f:
    json.dump(other_docs, f, indent=2, ensure_ascii=False)
with open('/home/user/mori-capital-2/scratch/update-docs.json', 'w') as f:
    json.dump(update_docs, f, indent=2, ensure_ascii=False)
print(f"\nSaved JSON — total {len(other_docs) + len(update_docs)} docs")
