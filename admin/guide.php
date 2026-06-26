<?php
require __DIR__ . '/../src/bootstrap.php';
use Mori\Auth;
use function Mori\e;
use function Mori\asset;

Auth::requireLogin();
$adminPage = ['title' => 'Admin Guide', 'crumb' => 'How to use the Mori Capital CMS'];
include __DIR__ . '/partials/layout-start.php';
?>
<style>
.guide { max-width: 860px; }
.guide h2 { font-size: 22px; margin: 32px 0 12px; padding-top: 20px; border-top: 1px solid var(--a-border); color: var(--a-navy); }
.guide h2:first-child { border-top: none; margin-top: 0; padding-top: 0; }
.guide h3 { font-size: 16px; margin: 20px 0 8px; color: var(--a-navy); }
.guide p { font-size: 14px; line-height: 1.7; color: var(--a-text-soft); margin-bottom: 12px; }
.guide ul, .guide ol { font-size: 14px; line-height: 1.7; color: var(--a-text-soft); margin-bottom: 14px; padding-left: 24px; }
.guide li { margin-bottom: 4px; }
.guide code { background: var(--a-border-soft); padding: 2px 6px; border-radius: 3px; font-size: 12px; }
.guide .screenshot { border: 1px solid var(--a-border); border-radius: 8px; margin: 14px 0 20px; max-width: 100%; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.guide .tip { background: #E8F8F4; border-left: 3px solid var(--a-teal); padding: 12px 16px; border-radius: 0 6px 6px 0; margin: 14px 0; font-size: 13px; color: #0F6B5C; }
.guide .warn { background: #FDF6EC; border-left: 3px solid var(--a-warning); padding: 12px 16px; border-radius: 0 6px 6px 0; margin: 14px 0; font-size: 13px; color: #8B5A00; }
.guide .toc { background: var(--a-border-soft); padding: 18px 24px; border-radius: 8px; margin-bottom: 24px; }
.guide .toc h3 { margin-top: 0; }
.guide .toc ol { margin-bottom: 0; }
.guide .toc a { color: var(--a-teal); text-decoration: none; font-weight: 500; }
.guide .toc a:hover { text-decoration: underline; }
</style>

<div class="guide">

<div class="toc">
    <h3>Table of Contents</h3>
    <ol>
        <li><a href="#login">Logging In</a></li>
        <li><a href="#dashboard">Dashboard</a></li>
        <li><a href="#homepage">Homepage Content</a></li>
        <li><a href="#hero">Hero Slider</a></li>
        <li><a href="#pages">Pages</a></li>
        <li><a href="#editors">Page Editors (TinyMCE / Block Builder / GrapesJS)</a></li>
        <li><a href="#insights">Mori Views (Insights)</a></li>
        <li><a href="#team">Team Members</a></li>
        <li><a href="#translations">Translation Center</a></li>
        <li><a href="#funds">Funds &amp; Share Classes</a></li>
        <li><a href="#performance">Performance (NAV) &mdash; CSV import &amp; benchmark toggle</a></li>
        <li><a href="#documents">Documents (dropdown of 4 sub-pages, drag-reorder)</a></li>
        <li><a href="#announcements">Fund Announcements</a></li>
        <li><a href="#media">Media Library</a></li>
        <li><a href="#messages">Messages (Contact Inbox)</a></li>
        <li><a href="#newsletter">Newsletter</a></li>
        <li><a href="#settings">Settings</a></li>
        <li><a href="#seo">SEO &amp; Custom Code</a></li>
        <li><a href="#users">Users</a></li>
        <li><a href="#audit">Audit Log</a></li>
        <li><a href="#database">Database &amp; Migrations</a></li>
        <li><a href="#bilingual">Bilingual Content (EN/DE)</a></li>
        <li><a href="#shortcuts">Keyboard Shortcuts</a></li>
    </ol>
</div>

<!-- 1. Login -->
<h2 id="login">1. Logging In</h2>
<p>Navigate to <code>/admin/</code> on your website. You will see the login screen:</p>
<img src="/docs/guide-screenshots/login.png" class="screenshot" alt="Login screen">
<p>Enter your <strong>email</strong> and <strong>password</strong>, then click <strong>Sign in</strong>. Passwords must be at least 12 characters. After 30 minutes of inactivity, you will be logged out automatically.</p>
<div class="tip"><strong>Tip:</strong> If you forget your password, ask a super_admin user to reset it from the Users page.</div>

<!-- 2. Dashboard -->
<h2 id="dashboard">2. Dashboard</h2>
<img src="/docs/guide-screenshots/dashboard.png" class="screenshot" alt="Dashboard">
<p>The dashboard is your starting point. It shows:</p>
<ul>
    <li><strong>Quick stats</strong> — total pages, funds, team members, documents, insights, unread messages</li>
    <li><strong>Recent activity</strong> — the latest audit log entries (who did what and when)</li>
    <li><strong>Quick links</strong> — shortcuts to the most common tasks</li>
</ul>
<p>The left sidebar provides navigation to all sections of the admin panel. The current page is highlighted in teal.</p>

<!-- 3. Homepage Content -->
<h2 id="homepage">3. Homepage Content</h2>
<img src="/docs/guide-screenshots/homepage-content.png" class="screenshot" alt="Homepage Content">
<p>Go to <strong>Content &rarr; Homepage Content</strong> to edit all text and numbers that appear on the homepage. Every editable copy field is shown as a paired <strong>EN / DE</strong> set so both language versions can be managed in one screen.</p>
<p>This page lets you manage:</p>
<ul>
    <li><strong>About Section</strong> — the company description paragraph (EN + DE), eyebrow label (EN + DE), quote (EN + DE), and image paths</li>
    <li><strong>Statistics &amp; Counters</strong> — the numbers shown in "25+ years", "200+ securities", "15+ markets", "80+ years collective experience". Change these once and they update everywhere on the site (homepage + about page + cinematic section)</li>
    <li><strong>Funds Section</strong> — description text (EN + DE) and footer note (EN + DE)</li>
    <li><strong>Investment Style Section</strong> — description, bullet points 1&ndash;2, feature items 1&ndash;3, and the quote, each with EN + DE pair</li>
    <li><strong>Cinematic Section</strong> — eyebrow / title / description (EN + DE), showcase fund name, NAV display, YTD %, 10Y annualised, and the three floating chips (Currency, 10Y Annualised, Regulatory)</li>
    <li><strong>Investment Principles</strong> — the 5 principle cards (icon class, title EN + DE, description EN + DE) shown on the Investment Style page</li>
</ul>
<div class="tip"><strong>Tip:</strong> Every field has a sensible default. If you leave a DE field empty, the site falls back to the EN value automatically. For bulk translation, use the <a href="#translations">Translation Center</a> &mdash; it lists every German field across the site in one screen.</div>
<div class="tip"><strong>Removing benchmark text:</strong> The "10Y chip suffix" field is where the &ldquo;vs benchmark&rdquo; text lives. Clear it and the suffix disappears from the homepage chip.</div>

<!-- 4. Hero Slider -->
<h2 id="hero">4. Hero Slider</h2>
<img src="/docs/guide-screenshots/hero-slider.png" class="screenshot" alt="Hero Slider">
<p>Go to <strong>Content &rarr; Hero Slider</strong> to manage the large banner area at the top of the homepage.</p>
<p>You can add multiple slides that auto-rotate every 6 seconds. Each slide supports:</p>
<ul>
    <li><strong>Image</strong> (JPG, PNG, WEBP) or <strong>Video</strong> (MP4) backgrounds</li>
    <li><strong>Title &amp; Subtitle (EN)</strong> — overlay text shown on the EN site</li>
    <li><strong>Title (DE) &amp; Subtitle (DE)</strong> — German overlay text. Leave blank to reuse the EN value. Editable from the slide form and from the <a href="#translations">Translation Center</a></li>
    <li><strong>CTA Button</strong> — optional call-to-action button (text + URL)</li>
    <li><strong>Overlay opacity</strong> — controls how dark the overlay is (0 = no overlay, 1 = fully dark)</li>
    <li><strong>Display order</strong> — controls the slide sequence</li>
    <li><strong>Active toggle</strong> — disable a slide without deleting it</li>
</ul>
<p>Click <strong>Upload</strong> to upload a new image or video directly from your computer. The file is stored in the media library automatically.</p>
<div class="warn"><strong>Recommended sizes:</strong> Images should be at least 1920&times;1080px for crisp display on large screens. Videos should be MP4 format, under 15MB, and ideally 10&ndash;15 seconds long.</div>

<!-- 5. Pages -->
<h2 id="pages">5. Pages</h2>
<img src="/docs/guide-screenshots/pages.png" class="screenshot" alt="Pages">
<p>Go to <strong>Content &rarr; Pages</strong> to see all static pages (About, Investment Style, Legal, Privacy, Cookies, etc.).</p>
<p>For each page you can:</p>
<ul>
    <li><strong>Edit</strong> — open in the classic TinyMCE editor or the visual block builder</li>
    <li><strong>Preview</strong> — see the page on the live site</li>
    <li><strong>Set status</strong> — Draft (hidden) or Published (visible)</li>
    <li><strong>Set language</strong> — EN or DE</li>
    <li><strong>Delete</strong> — permanently remove the page</li>
</ul>

<!-- 6. Editors -->
<h2 id="editors">6. Page Editors</h2>
<p>Mori Capital offers <strong>three editing modes</strong> for pages:</p>

<h3>Classic Editor (TinyMCE)</h3>
<p>A familiar WYSIWYG editor like Microsoft Word. Best for simple text pages (Legal, Privacy, etc.).</p>
<ul>
    <li>Bold, italic, headings, lists, links, images</li>
    <li>SEO fields: meta title, meta description</li>
    <li>Inline image upload via toolbar</li>
</ul>

<h3>Block Builder</h3>
<img src="/docs/guide-screenshots/page-builder.png" class="screenshot" alt="Block Builder">
<p>A Notion/Squarespace-style block editor. Best for visually rich pages.</p>
<ul>
    <li><strong>12 block types:</strong> Heading, Text, Image, Button, Columns, Spacer, Divider, Quote, List, Video, HTML, Section</li>
    <li>Drag blocks to reorder them</li>
    <li>Click any text to edit it inline</li>
    <li>Select text to see the formatting toolbar (bold, italic, link)</li>
    <li>Responsive preview: Desktop / Tablet / Mobile toggle</li>
    <li>Undo/Redo support (Ctrl+Z / Ctrl+Y)</li>
    <li>Auto-save every 60 seconds</li>
</ul>

<h3>GrapesJS Editor</h3>
<p>A pixel-level visual editor for advanced users. Best for fully custom layouts.</p>
<ul>
    <li>Drag-and-drop blocks from the right panel</li>
    <li>Style Manager: edit CSS properties (layout, spacing, typography, background, etc.)</li>
    <li>Layers panel: see the page structure as a tree</li>
    <li>Responsive: switch between Desktop, Tablet, Mobile views</li>
    <li>Undo/Redo, fullscreen mode, preview</li>
</ul>

<div class="tip"><strong>Tip:</strong> All three editors save to the same database field. You can switch between them freely — content is preserved.</div>

<!-- 7. Insights -->
<h2 id="insights">7. Mori Views (Insights)</h2>
<img src="/docs/guide-screenshots/insights.png" class="screenshot" alt="Insights">
<p>Go to <strong>Content &rarr; Mori Views</strong> to manage blog posts, market outlooks, factsheets, and press releases.</p>
<ul>
    <li><strong>Create:</strong> Click "New insight" &rarr; fill in title, slug, content (TinyMCE), category, locale, cover image</li>
    <li><strong>Categories:</strong> Outlook, Factsheet, Shareholder Notice, Article, Press</li>
    <li><strong>Status:</strong> Draft or Published</li>
    <li><strong>Publish date:</strong> Controls the display order on the insights page</li>
</ul>

<!-- 8. Team -->
<h2 id="team">8. Team Members</h2>
<img src="/docs/guide-screenshots/team.png" class="screenshot" alt="Team">
<p>Go to <strong>Content &rarr; Team</strong> to manage team member profiles.</p>
<ul>
    <li><strong>Photo:</strong> Upload via the form (JPG/PNG/WEBP)</li>
    <li><strong>Bio:</strong> English and German versions</li>
    <li><strong>Title:</strong> English and German (e.g. "Director, Portfolio Manager" / "Direktor, Portfoliomanager")</li>
    <li><strong>LinkedIn URL &amp; Email:</strong> Shown on the team page</li>
    <li><strong>Display order:</strong> Controls the sequence on the page</li>
</ul>

<!-- 9. Translation Center -->
<h2 id="translations">9. Translation Center</h2>
<p>Go to <strong>Content &rarr; Translation Center</strong> for a single dashboard listing every German content field across the entire site.</p>
<p>Each row shows the English source on the left and the German editor on the right. Edits save automatically when you click out of the field (no Save button to remember). The whole site is covered in seven sections:</p>
<ul>
    <li><strong>Funds</strong> &mdash; <code>name_de</code>, <code>description_de</code>, <code>objective_de</code> for each fund</li>
    <li><strong>Team Members</strong> &mdash; <code>title_de</code> and <code>bio_de</code> for every active team member</li>
    <li><strong>Homepage Copy</strong> &mdash; every editable hp_* setting (About / Funds / Style / Cinematic) and the 5 Investment Principles</li>
    <li><strong>Pages (DE versions)</strong> &mdash; title, meta description and HTML body for every page that has a German row</li>
    <li><strong>Mori Views / Insights</strong> &mdash; title, excerpt and body for every published EN article paired with a DE counterpart</li>
    <li><strong>Hero Slider</strong> &mdash; per-slide title and subtitle in German</li>
    <li><strong>Fund Announcements</strong> &mdash; title and body for announcements that have a DE row</li>
</ul>
<p><strong>How the two columns are presented:</strong></p>
<ul>
    <li>For <strong>plain text fields</strong> (titles, eyebrows, excerpts, meta descriptions) the EN source is shown as escaped text and the DE side is a simple input or textarea.</li>
    <li>For <strong>HTML body fields</strong> (page bodies, insight bodies, announcement bodies) the EN source is shown as a <em>rendered preview</em> &mdash; headings, paragraphs, lists, tables exactly as they appear on the public site &mdash; and the DE side is a full <strong>TinyMCE WYSIWYG editor</strong> with bold/italic, headings (H2&ndash;H4), lists, link, image, table, and a raw-HTML <em>code</em> button for power users.</li>
</ul>
<div class="tip"><strong>Tip:</strong> The German content shipped with the site has already been hand-translated. Use the Translation Center to polish wording or fill in newly added content; you won&rsquo;t need to translate from scratch.</div>
<p>A sticky table-of-contents row across the top lets you jump straight to any section (Funds, Team, Homepage Copy, etc.).</p>

<!-- 10. Funds -->
<h2 id="funds">10. Funds &amp; Share Classes</h2>
<img src="/docs/guide-screenshots/funds.png" class="screenshot" alt="Funds">
<p>Go to <strong>Funds &rarr; Funds &amp; Share Classes</strong>.</p>
<p>This page manages the two funds (Mori Eastern European Fund, Mori Ottoman Fund) and their share classes.</p>
<ul>
    <li><strong>Edit fund:</strong> Click the pencil icon &rarr; change name, description (EN/DE), cover image, status</li>
    <li><strong>Share classes:</strong> Each fund has multiple share classes (e.g. EE Class A EUR, Otto Class B USD). Edit ISIN, currency, launch date</li>
    <li><strong>Add share class:</strong> Click "Add share class" and fill the modal form</li>
</ul>
<div class="tip"><strong>Note:</strong> Fund names appear automatically in the website header, footer, and fund cards — no need to update them in multiple places.</div>

<!-- 10. Performance -->
<h2 id="performance">11. Performance (NAV) &mdash; CSV import &amp; benchmark toggle</h2>
<img src="/docs/guide-screenshots/performance.png" class="screenshot" alt="Performance">
<p>Go to <strong>Funds &rarr; Performance (NAV)</strong> to manage NAV history for each share class. The page has four cards: <em>Selector</em>, <em>Chart display</em>, <em>Bulk import (CSV)</em>, and the entries table at the bottom.</p>

<h3>Manual NAV entry</h3>
<ul>
    <li>Select a <strong>fund</strong> and <strong>share class</strong> at the top</li>
    <li>Enter the <strong>date</strong> and <strong>NAV value</strong>, then click <strong>Add entry</strong></li>
</ul>

<h3>Bulk CSV import</h3>
<p>Click <strong>Download template</strong> at the top of the <em>Bulk import (CSV)</em> card to grab a ready-to-fill <code>mori-nav-template.csv</code> with the right columns and example rows. The required columns are:</p>
<ul>
    <li><code>date</code> &mdash; in <code>YYYY-MM-DD</code> format</li>
    <li><code>nav</code> &mdash; numeric value</li>
    <li><code>benchmark</code> &mdash; optional, leave blank if not tracked</li>
</ul>
<p>When you upload, pick one of three modes that controls how existing data is handled:</p>
<ul>
    <li><strong>Update existing + add new</strong> (default, recommended) &mdash; if a date in the CSV already exists, its NAV is overwritten with the new value; new dates are inserted; nothing is deleted. Safe for monthly amendments.</li>
    <li><strong>Add new only</strong> &mdash; existing dates are kept untouched, only brand-new dates are inserted.</li>
    <li><strong>Replace all</strong> &mdash; wipes every NAV entry for that share class first, then imports the CSV. Use this when you want a complete clean slate (e.g. to clear placeholder demo numbers on first upload).</li>
</ul>
<div class="warn"><strong>Replace mode is destructive:</strong> all existing entries for the selected share class are deleted before the import runs. The deletion is audit-logged so you can see what was removed, but there is no &ldquo;undo&rdquo;.</div>

<h3>Hiding the benchmark line</h3>
<p>The <em>Chart display</em> card has a single checkbox: <strong>Show benchmark on the NAV chart</strong>. Untick it and the benchmark line disappears from every share-class chart on the public site. Your benchmark numbers stay in the database, so you can switch the display back on at any time. The cinematic chip on the homepage (&ldquo;vs benchmark&rdquo; text) is cleared separately from <a href="#homepage">Homepage Content &rarr; Cinematic Section &rarr; 10Y chip suffix</a>.</p>

<p>The Performance page on the website displays an interactive ApexChart generated from this data, plus cumulative returns (YTD, 1Y, 3Y, 5Y, 10Y).</p>

<!-- 11. Documents -->
<h2 id="documents">12. Documents (dropdown, drag-reorder)</h2>
<img src="/docs/guide-screenshots/documents.png" class="screenshot" alt="Documents">
<p>Go to <strong>Funds &rarr; Documents</strong> to manage every PDF on the site. The public side serves these through a dropdown with four sub-pages:</p>
<ul>
    <li><strong>Share Class Documents</strong> &mdash; the FundHub matrix (Prospectus, KIID, PRIIPs, Annual / Semi-Annual Accounts, Factsheet) keyed per share class</li>
    <li><strong>Company Policies</strong> &mdash; the annual policy set (Best Execution, ESG, Conflicts of Interest, etc.)</li>
    <li><strong>Other Documents</strong> &mdash; year-grouped list (2024 / 2025 / 2026) of audited financials, interim financials, UK Tax Reporting, etc.</li>
    <li><strong>Updates During Suspension</strong> &mdash; quarterly fund updates and shareholder notices from the suspension period onwards</li>
</ul>

<h3>Filtering the admin list</h3>
<p>The top of the page has three filters: <strong>Section</strong>, <strong>Fund</strong>, <strong>Type</strong>. Picking a Section narrows the table to one of the four public lists above and unlocks drag-to-reorder (see below).</p>

<h3>Drag-to-reorder</h3>
<p>Once a Section is selected, each row gets a grip handle on the left. Drag any row up or down and the new order is saved automatically &mdash; the public site reflects the change instantly. The initial ordering is seeded from the current view (newest first), so nothing reshuffles when you first open the screen.</p>
<ul>
    <li>Reordering is <strong>per-section</strong>: moving a Company Policy doesn&rsquo;t affect Other Documents or vice versa.</li>
    <li><strong>Other Documents</strong> keeps its year groupings; inside each year, your manual order takes priority.</li>
    <li>Every reorder is recorded in the Audit Log.</li>
</ul>

<h3>Uploading a new document</h3>
<p>Click <strong>Upload</strong> in the top-right. The form is laid out so the choices are explicit:</p>
<ul>
    <li><strong>Category</strong> &mdash; determines which public list the document appears on (Share Class / Company Policy / Other Document / Updates During Suspension).</li>
    <li><strong>Scope</strong> &mdash; a three-mode radio selector that controls who sees the document in the FundHub matrix:
        <ul>
            <li><em>Per share class</em> &mdash; for KIIDs and PRIIPs KIDs. Tick one or more share classes; each one sees its own file.</li>
            <li><em>Per fund</em> &mdash; for Factsheets (one file per fund, served to every share class of that fund).</li>
            <li><em>Umbrella</em> &mdash; for Prospectus, Audited Accounts and Semi-Annual Accounts (applies to every share class).</li>
        </ul>
    </li>
    <li><strong>Document type</strong> &mdash; Prospectus, KIID, PRIIPs KID, Annual / Semi-Annual Report, Factsheet, Marketing, Other</li>
    <li><strong>Language</strong> &mdash; defaults to <em>Both</em> (a single file shown on EN and DE sites). Pick <em>English only</em> or <em>Deutsch only</em> when you have separate language versions of the same document.</li>
    <li><strong>Display year</strong> &mdash; sets the year bucket on the Other Documents page (defaults to document date&rsquo;s year)</li>
    <li><strong>Description</strong> &mdash; shown to users on the Company Policies and Other Documents listings</li>
</ul>
<div class="tip"><strong>Tip on KIIDs:</strong> Choose <em>Per share class</em> scope, tick the relevant share class(es), set Document type to <em>KIID</em>, and pick <em>English</em> or <em>Deutsch</em>. The matrix then surfaces that file in the right cell on the right language site.</div>

<!-- 12. Fund Announcements -->
<h2 id="announcements">13. Fund Announcements</h2>
<p>Go to <strong>Funds &rarr; Fund Announcements</strong> to publish short narrative notices on the public <code>/announcements.php</code> page. The link sits between <em>Documents</em> and <em>Contact</em> in the site navigation.</p>
<p>Each announcement supports:</p>
<ul>
    <li><strong>Title</strong> and <strong>slug</strong> (the slug auto-generates from the title and feeds the URL)</li>
    <li><strong>Publish date</strong> &mdash; controls chronological order on the public page</li>
    <li><strong>Status</strong> &mdash; Draft or Published</li>
    <li><strong>Language</strong> &mdash; Both / English only / Deutsch only</li>
    <li><strong>Related fund</strong> (optional) &mdash; tags the announcement to one of the two funds</li>
    <li><strong>Attached document</strong> (optional) &mdash; pick a PDF from the Documents library to link below the body</li>
    <li><strong>Body</strong> &mdash; a rich-text TinyMCE editor for the narrative content</li>
</ul>
<p>Published announcements appear chronologically (newest first) on the public page. Use the language filter on the upload form to maintain separate EN and DE versions; or use the <a href="#translations">Translation Center</a> to fine-tune translations later.</p>

<!-- 13. Media -->
<h2 id="media">14. Media Library</h2>
<img src="/docs/guide-screenshots/media.png" class="screenshot" alt="Media Library">
<p>Go to <strong>Content &rarr; Media Library</strong> to manage all uploaded files.</p>
<ul>
    <li><strong>Upload:</strong> JPG, PNG, GIF, WEBP images and MP4/WebM videos</li>
    <li><strong>Folder:</strong> Optionally tag uploads by folder (hero, team, insights, etc.)</li>
    <li><strong>Delete:</strong> Removes the file from the server and database</li>
</ul>
<p>Uploaded files are stored in <code>/uploads/media/YEAR/</code>.</p>

<!-- 13. Messages -->
<h2 id="messages">15. Messages (Contact Inbox)</h2>
<img src="/docs/guide-screenshots/messages.png" class="screenshot" alt="Messages">
<p>Go to <strong>Engagement &rarr; Messages</strong> to see messages submitted via the contact form.</p>
<ul>
    <li>View sender name, email, subject, message, and submission date</li>
    <li><strong>Reply:</strong> Click the reply button to send a branded email response directly from the admin panel</li>
    <li>Replies are sent via SMTP (configure in Settings)</li>
</ul>

<!-- 14. Newsletter -->
<h2 id="newsletter">16. Newsletter</h2>
<img src="/docs/guide-screenshots/newsletter.png" class="screenshot" alt="Newsletter">
<p>Go to <strong>Engagement &rarr; Newsletter</strong> to manage subscribers and send bulk emails.</p>
<ul>
    <li><strong>Subscribers list:</strong> See all subscribers with email, language preference, status, dates</li>
    <li><strong>Export CSV:</strong> Download the full subscriber list</li>
    <li><strong>Delete:</strong> Remove individual subscribers</li>
    <li><strong>Send Newsletter:</strong> Go to the Send page to compose and send a newsletter</li>
</ul>

<h3>Sending a Newsletter</h3>
<ol>
    <li>Choose a <strong>template</strong>: Branded (Mori styled), Minimal, or Plain Text</li>
    <li>Enter a <strong>subject line</strong></li>
    <li>Write the <strong>body content</strong></li>
    <li>Optionally filter by <strong>locale</strong> (EN only, DE only, or all)</li>
    <li>Click <strong>Send test to my email</strong> first to verify</li>
    <li>When ready, click <strong>Send to all subscribers</strong> — a progress bar shows the status</li>
</ol>

<!-- 17. Settings -->
<h2 id="settings">17. Settings</h2>
<img src="/docs/guide-screenshots/settings.png" class="screenshot" alt="Settings">
<p>Go to <strong>System &rarr; Settings</strong> to manage site-wide configuration.</p>
<ul>
    <li><strong>General:</strong> Site title, tagline, default language</li>
    <li><strong>Contact:</strong> Email, phone, address</li>
    <li><strong>Compliance:</strong> Regulator name, license number</li>
    <li><strong>SEO:</strong> Default meta title/description, Google Analytics ID, OG image</li>
    <li><strong>SMTP:</strong> Email configuration is in the <code>.env</code> file on the server (not in this form). Use the "Send test" button to verify it works</li>
    <li><strong>Logo:</strong> Upload light and dark versions of the logo</li>
    <li><strong>Security:</strong> Session timeout (minutes), upload max size (MB)</li>
</ul>

<!-- 18. SEO -->
<h2 id="seo">18. SEO &amp; Custom Code</h2>
<img src="/docs/guide-screenshots/seo.png" class="screenshot" alt="SEO">
<p>Go to <strong>System &rarr; SEO &amp; Code</strong> to manage advanced SEO settings and code injection.</p>
<ul>
    <li><strong>Custom &lt;head&gt; code:</strong> Paste Google Tag Manager, Meta Pixel, or any script that goes in the HTML &lt;head&gt;</li>
    <li><strong>Custom footer code:</strong> Paste scripts that go before &lt;/body&gt;</li>
    <li><strong>Robots.txt:</strong> Managed automatically — blocks admin, API, source, and database directories</li>
    <li><strong>Sitemap:</strong> Auto-generated at <code>/sitemap.xml</code> with all published pages and insights</li>
</ul>

<!-- 19. Users -->
<h2 id="users">19. Users</h2>
<img src="/docs/guide-screenshots/users.png" class="screenshot" alt="Users">
<p>Go to <strong>System &rarr; Users</strong> to manage admin panel users.</p>
<ul>
    <li><strong>Roles:</strong> <code>super_admin</code> (full access) or <code>editor</code> (content only)</li>
    <li><strong>Create user:</strong> Email + password (min 12 chars)</li>
    <li><strong>Change password:</strong> Enter a new password in the edit modal</li>
    <li><strong>Delete:</strong> Remove a user permanently</li>
</ul>
<div class="warn"><strong>Important:</strong> Only super_admin users can access Settings, Users, SEO, and Database pages.</div>

<!-- 20. Audit -->
<h2 id="audit">20. Audit Log</h2>
<img src="/docs/guide-screenshots/audit.png" class="screenshot" alt="Audit Log">
<p>Go to <strong>System &rarr; Audit Log</strong> to see a complete history of all actions taken in the admin panel.</p>
<ul>
    <li>Every create, update, delete, login, and settings change is recorded</li>
    <li><strong>Search:</strong> Filter by text, action type, or date range</li>
    <li>Shows who did what, when, and on which record</li>
</ul>

<!-- 21. Database -->
<h2 id="database">21. Database &amp; Migrations</h2>
<img src="/docs/guide-screenshots/database.png" class="screenshot" alt="Database">
<p>Go to <strong>System &rarr; Database</strong> to manage the database schema.</p>
<ul>
    <li><strong>Schema sync:</strong> Re-runs the base schema (safe — uses CREATE TABLE IF NOT EXISTS)</li>
    <li><strong>Migrations:</strong> Shows pending and applied migration files. Click "Run" to apply pending migrations</li>
</ul>
<div class="warn"><strong>Note:</strong> This is an advanced feature. Normally, running <code>install.php</code> handles everything automatically.</div>

<!-- 22. Bilingual -->
<h2 id="bilingual">22. Bilingual Content (EN/DE)</h2>
<p>The site supports English and German. Here's how bilingual content works:</p>
<ul>
    <li><strong>Language switcher:</strong> Visitors click EN/DE in the topbar (desktop) or mobile menu to switch</li>
    <li><strong>Pages:</strong> Create separate EN and DE versions with the same slug</li>
    <li><strong>Funds &amp; Team:</strong> Each record has <code>name_en</code>, <code>name_de</code>, <code>description_en</code>, <code>description_de</code> fields</li>
    <li><strong>Homepage text:</strong> The Homepage Content page has separate EN and DE fields for descriptions</li>
    <li><strong>UI labels:</strong> Managed in <code>src/lang/en.php</code> and <code>src/lang/de.php</code> — these are the menu labels, button text, etc.</li>
</ul>

<!-- 23. Shortcuts -->
<h2 id="shortcuts">23. Keyboard Shortcuts</h2>
<table class="a-table" style="max-width:400px;">
    <thead><tr><th>Shortcut</th><th>Action</th></tr></thead>
    <tbody>
        <tr><td><code>Ctrl + S</code></td><td>Save (in page editors)</td></tr>
        <tr><td><code>Ctrl + Z</code></td><td>Undo (Block Builder)</td></tr>
        <tr><td><code>Ctrl + Y</code></td><td>Redo (Block Builder)</td></tr>
        <tr><td><code>Escape</code></td><td>Deselect block</td></tr>
        <tr><td><code>Delete</code></td><td>Delete selected block</td></tr>
    </tbody>
</table>

<div style="margin-top:40px;padding:24px;background:var(--a-border-soft);border-radius:8px;text-align:center;">
    <p style="color:var(--a-muted);font-size:13px;margin:0;">Mori Capital Management Ltd. CMS — Admin Guide v1.0<br>
    Last updated: <?= date('d M Y') ?></p>
</div>

</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
