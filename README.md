# Mori Capital Management — Website + CMS

Independent EEMEA specialist asset manager. Public website with bilingual
content (English / Deutsch), a fund document hub, a NAV-driven performance
viewer, and a full admin panel for content, documents and users.

> Live deploy guide: see [`DEPLOY.md`](DEPLOY.md).
> Brand & demo handoff: open `demo-guide.html` in a browser.

---

## Stack

- **PHP 8.2+** (no framework — small, auditable, deploy-friendly)
- **MariaDB 10.5+ / MySQL 8** (PDO, prepared statements throughout)
- **OpenLiteSpeed / Apache** (`.htaccess` works on both)
- **TinyMCE 7** (open-source GPL build) — WYSIWYG editor
- **ApexCharts** — performance chart
- **Bootstrap 5 grid + Swiper + WOW.js** — frontend theme primitives
- **Inter / DM Sans** — typography
- **Argon2id** — password hashing
- **CSRF tokens + Argon2id sessions** — authentication
- **bilingual EN / DE** content storage at the column level

---

## Project layout

```
.
├── index.php · about.php · investment-style.php
├── fund-eastern-european.php · fund-ottoman.php · fund-performance.php
├── documents.php · contact.php · team.php · insights.php · insight.php
├── legal.php · privacy.php · cookies.php
├── admin/                   ← CMS (login, dashboard, pages, documents,
│                              funds, share classes, NAV, team, insights,
│                              users, media, settings, messages, audit)
├── api/                     ← public AJAX (newsletter, document download)
├── src/                     ← PHP classes (denied via .htaccess)
│   ├── Config.php · Database.php · Auth.php · Csrf.php
│   ├── AuditLog.php · I18n.php · helpers.php · bootstrap.php
│   ├── partials/            ← shared head/topbar/header/footer/scripts
│   └── lang/                ← en.php, de.php
├── db/                      ← schema.sql + seed.sql (denied via .htaccess)
├── uploads/                 ← user content (PHP execution disabled)
├── assets/, css/, js/, images/
├── install.php              ← one-time setup wizard
├── .env.example             ← copy to .env and fill in
└── .htaccess                ← rewrites + security headers
```

---

## Features

### Public site
- **Homepage** — hero with EEMEA imagery (Istanbul), about, two fund cards,
  the Mori Style principles, cinematic mouse-tilt dashboard, Mori Views
  (latest insights), team (6 members), footer with MFSA badge + disclaimer
- **Bilingual content** — EN/DE switcher in the topbar, persisted via cookie
- **Investor type gate** — first-visit modal asking visitor to confirm they
  are not a U.S. person (EU AIFMD-style compliance)
- **Cookie consent** — GDPR-style bottom banner
- **Fund pages** — overview, share-class tables, fund documents
- **Performance** — NAV chart with ApexCharts, fund + share-class selectors,
  1M/3M/6M/1Y/3Y/5Y/Max range pills, cumulative returns table
- **Document Hub** — filterable by fund / type / search, tracked downloads
- **Contact form** — CSRF + honeypot + server validation + flash messages
- **Insights / Mori Views** — listing with category filter + article detail
- **Team page** — alternating layout with bios and social links
- **Legal / Privacy / Cookies** — DB-driven, editable from admin

### Admin panel
- Argon2id password hashing, session-based auth, CSRF on every POST
- Three roles: `super_admin`, `editor`, `doc_manager`
- Audit log — every admin action is recorded
- **Dashboard** — stats, quick actions, recent messages, recent activity
- **Pages** — list / create / edit / delete with TinyMCE WYSIWYG and SEO meta
- **Documents** — upload with type/fund/locale tagging, share-class multi-select,
  download tracking, year-bucketed storage
- **Funds & Share Classes** — inline edit with modals
- **Performance** — NAV entry per share class + CSV bulk import
- **Team** — bilingual bios with TinyMCE
- **Mori Views** — full WYSIWYG editor for insights / outlooks / shareholder notices
- **Messages** — contact-form inbox with status (new / read / archived)
- **Newsletter** — subscribers list + CSV export
- **Media library** — image upload, grid view, copy URL, delete
- **Users** (super_admin only) — full CRUD
- **Settings** (super_admin only) — site title, contact, compliance, SEO,
  social, security, uploads
- **Audit log** (super_admin only) — last 300 admin actions

---

## Security checklist

- [x] Argon2id password hashing (rehash on parameter change)
- [x] CSRF tokens on every POST form
- [x] Session cookies with HttpOnly, Secure (HTTPS), SameSite=Lax
- [x] Session fingerprint (UA + IP prefix) + inactivity timeout
- [x] Session ID regenerated on login
- [x] Prepared statements throughout (`PDO::ATTR_EMULATE_PREPARES = false`)
- [x] File upload allowlist by extension + size cap + safe storage path
- [x] PHP execution disabled in `/uploads/` via `.htaccess`
- [x] `/src/` and `/db/` denied direct web access
- [x] Audit log of all admin actions (with user, IP, UA)
- [x] X-Frame-Options DENY on admin pages
- [x] Security headers in root `.htaccess`
- [x] `display_errors = 0` in production
- [x] Investor gate + cookie consent stored in `localStorage` (not server-side
  to keep it lightweight + cookie-law compliant)

---

## Install

See [`DEPLOY.md`](DEPLOY.md) for the full CyberPanel setup. Short version:

```bash
git clone https://github.com/densey/mori-capital-2.git
cp .env.example .env && nano .env     # set DB creds
# Visit https://your-domain.tld/install.php and follow the 4-step wizard
rm install.php                         # delete after install
```

---

## Local development

```bash
# Built-in PHP server (anywhere with PHP 8.2 + MySQL)
php -S 127.0.0.1:8080
# Then visit http://127.0.0.1:8080/install.php
```

---

## Updating content

All page bodies, fund descriptions, team bios, insights, share-class metadata
and NAV history are editable from `/admin/` — **no code changes required.**

The only content NOT in the database (yet) is:
- The homepage hero image (currently `assets/images/hero/hero-istanbul.jpg`)
- The cinematic showcase numbers (`15+`, `200+`, `25+` — hardcoded in `index.php`)
- The "Mori Style" principles (hardcoded — they form the brand voice)

These can be moved to settings later if needed.

---

## License & content

Code: proprietary — © Mori Capital Management Ltd.
Theme base: Covar template (adapted with permission).
Brand assets, logos, fund documentation, team photos: all rights reserved.
