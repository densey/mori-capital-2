# Mori Capital — Homepage Demo Guide

> Open `demo-guide.html` in your browser for the full visual style guide.
> Open `index.html` to view the live homepage demo.

**Project:** Mori Capital Management website redesign
**Scope (this demo):** Homepage only · single page (`index.html`)
**Languages:** EN active · DE planned
**Compliance:** GDPR cookie consent + EU investor type gate
**Responsive:** 4 breakpoints (1199 / 991 / 767 / 480)

---

## Brand palette

| Token | Hex | Role |
|---|---|---|
| **Mori Teal** | `#1ABC9C` | Brand · CTAs · sub-titles · links · active states |
| **Teal Dark** | `#16A085` | Hover / pressed |
| Navy | `#1B3A5C` | Headings · footer · sticky header · structural dark |
| Navy Dark | `#122842` | Cinematic backdrop · cookie banner · gradient base |
| Text | `#5A6B7B` | Body copy |
| Award Gold | `#B8935A` | Award badges only |
| Background | `#FFFFFF` | |
| Soft / Tint | `#F5F7FA` / `#EEF3F8` | Section alt backgrounds |
| Border | `#E1E7EE` | Dividers |

## Typography

- **Family:** `'Inter', 'DM Sans', system-ui, …`
- **H1 (hero):** clamp 28→44px, weight 700, line-height 1.08
- **H2 (section):** clamp 24→38px, weight 700
- **H3 (card):** 20–24px, weight 600
- **Body:** 15–17px, line-height 1.6, weight 400
- **Eyebrow:** 11–12px, weight 700, letter-spacing 0.18em, **teal**

---

## Homepage sections (in order)

1. **Topbar** — phone · email · hours · Investor Login · EN/DE switcher
2. **Header (sticky)** — prominent 80px logo · main nav · Document Hub CTA · transparent over hero, navy on scroll
3. **Hero with video** — Mori cinematic loop · headline · "Explore Funds" CTA
4. **About Mori** — intro · pull-quote · counters (25+ years, 200+ securities)
5. **Funds (2-card grid)** — Eastern European Fund · Ottoman Fund · PMs in section footer
6. **The Mori Style** — 4 principles · Aziz Unan pull-quote
7. **Cinematic 3D showcase** — mouse-tilt dashboard card · floating chips · navy backdrop
8. **Awards & Rating Bodies** — 4 highlight cards + Sauren / FERI / Morningstar / FundStars / Quantalys strip
9. **Mori Views — Latest insights** — 3-card grid (Outlook · Factsheet · Shareholder Notice)
10. **Team (6 members)** — Aziz Unan · Özlem Tümer Eke · Desmond Riordan · Isidro Garcia De La Torre · Dr. Peter Zurhorst · Jean-Paul Gauci
11. **Footer** — contact · Mori Capital · Funds & Documents · Newsletter · MFSA C66999 badge · regulatory disclaimer

---

## Compliance & trust

- **Investor type gate** (first visit) — confirm "not a U.S. person" + Retail/Professional choice
- **Cookie consent** (GDPR) — Accept all / Necessary only · stored in localStorage
- **MFSA badge** — *Regulated by MFSA · Firm Reference C66999* in footer CTA strip
- **Footer disclaimer** — MiFID auth · non-U.S. persons · 1940 Act · past performance warning · prospectus reference
- **Risk warnings** — embedded in disclaimer

---

## Motion & interaction

- **Hero video** — looped, muted, autoplay · poster fallback (`hero-istanbul.jpg`)
- **3D card** — ±9° mouse-tilt with lerp smoothing · soft sin/cos idle drift · multi-layer parallax
- **Sticky header** — transparent → navy on scroll · logo eases 80→64px
- **WOW.js reveals** — staggered fade-up on scroll · counters animate from 0
- **Card hover** — 4px lift + teal border · image zoom 1.04
- **Reduce-motion safe** — full media query support

---

## Responsive behaviour

| Breakpoint | Header | Hero h1 | Funds | Awards | Team | Footer |
|---|---|---|---|---|---|---|
| Desktop > 1199 | Transparent · 80px logo | 44px | 2-col | 4-col | 3-col | 4-col |
| Tablet 991–1199 | Same · 60px | 38px | 2-col | 4-col | 3-col | 4-col |
| Phone 480–767 | **Solid teal · drawer · 60px** | 28px | 1-col | 2-col | 2-col | 1-col stack |
| Small < 480 | **Solid teal · 44px** | 24px | 1-col | 1-col | 1-col | 1-col stack |

---

## Demo notes

- **Single page:** only `index.html` renders. Sub-page links show a "coming soon" toast.
- **Live links:** `mailto:` and `tel:` work normally.
- **Reset gate + cookie banner:** DevTools console →
  ```js
  localStorage.clear(); location.reload();
  ```
- **Forms** are decorative (no backend wired in yet).

## Files in the demo

```
index.html
demo-guide.html        ← this guide (HTML version)
DEMO-GUIDE.md          ← this guide (markdown)
assets/images/         Mori logos, hero/, service/, team/
css/mori.css           palette + Mori-specific overrides
css/custom.css         theme base
images/                favicon
js/                    theme libraries
```

---

## Roadmap (after homepage approval)

**Public pages:**
About Mori · Investment Style · Mori Eastern European Fund · Mori Ottoman Fund · Fund Performance · FundHub / Documents · Team · Awards · Mori Views · Contact · Legal & Disclaimer · DE translation of all pages

**Admin panel (CMS):**
Login & roles · Page editor (EN+DE) · Document management (fund / share class / type) · Performance / NAV entry · Fund & share-class CRUD · Media library · Settings (SEO, GA, backups)

---

*Mori Capital Management Ltd. · Regent House, Office 35, Bisazza Street, Sliema SLM 1640, Malta · Regulated by MFSA · C66999*
