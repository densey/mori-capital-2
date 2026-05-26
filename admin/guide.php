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
        <li><a href="#funds">Funds &amp; Share Classes</a></li>
        <li><a href="#performance">Performance (NAV)</a></li>
        <li><a href="#documents">Documents (FundHub)</a></li>
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
<p>The dashboard is your starting point. It shows:</p>
<ul>
    <li><strong>Quick stats</strong> — total pages, funds, team members, documents, insights, unread messages</li>
    <li><strong>Recent activity</strong> — the latest audit log entries (who did what and when)</li>
    <li><strong>Quick links</strong> — shortcuts to the most common tasks</li>
</ul>
<p>The left sidebar provides navigation to all sections of the admin panel. The current page is highlighted in teal.</p>

<!-- 3. Homepage Content -->
<h2 id="homepage">3. Homepage Content</h2>
<p>Go to <strong>Content &rarr; Homepage Content</strong> to edit all text and numbers that appear on the homepage.</p>
<p>This page lets you manage:</p>
<ul>
    <li><strong>About Section</strong> — the company description paragraph, the quote, and the image paths</li>
    <li><strong>Statistics &amp; Counters</strong> — the numbers shown in "25+ years", "200+ securities", etc. Change these once and they update everywhere on the site (homepage + about page + cinematic section)</li>
    <li><strong>Funds Section</strong> — the description text that appears above the fund cards</li>
    <li><strong>Investment Style Section</strong> — the description, bullet points, feature items, and the quote</li>
    <li><strong>Cinematic Section</strong> — the animated 3D showcase section with NAV data display</li>
    <li><strong>Investment Principles</strong> — the 5 principle cards shown on the Investment Style page (icon, title, description)</li>
</ul>
<div class="tip"><strong>Tip:</strong> Every field has a sensible default. If you leave a field empty, the site uses the built-in English default. Fill in the "DE" fields to show German translations when the user switches language.</div>

<!-- 4. Hero Slider -->
<h2 id="hero">4. Hero Slider</h2>
<p>Go to <strong>Content &rarr; Hero Slider</strong> to manage the large banner area at the top of the homepage.</p>
<p>You can add multiple slides that auto-rotate every 6 seconds. Each slide supports:</p>
<ul>
    <li><strong>Image</strong> (JPG, PNG, WEBP) or <strong>Video</strong> (MP4) backgrounds</li>
    <li><strong>Title &amp; Subtitle</strong> — overlay text shown on the slide</li>
    <li><strong>CTA Button</strong> — optional call-to-action button (text + URL)</li>
    <li><strong>Overlay opacity</strong> — controls how dark the overlay is (0 = no overlay, 1 = fully dark)</li>
    <li><strong>Display order</strong> — controls the slide sequence</li>
    <li><strong>Active toggle</strong> — disable a slide without deleting it</li>
</ul>
<p>Click <strong>Upload</strong> to upload a new image or video directly from your computer. The file is stored in the media library automatically.</p>
<div class="warn"><strong>Recommended sizes:</strong> Images should be at least 1920×1080px for crisp display on large screens. Videos should be MP4 format, under 15MB, and ideally 10–15 seconds long.</div>

<!-- 5. Pages -->
<h2 id="pages">5. Pages</h2>
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
<p>Go to <strong>Content &rarr; Mori Views</strong> to manage blog posts, market outlooks, factsheets, and press releases.</p>
<ul>
    <li><strong>Create:</strong> Click "New insight" &rarr; fill in title, slug, content (TinyMCE), category, locale, cover image</li>
    <li><strong>Categories:</strong> Outlook, Factsheet, Shareholder Notice, Article, Press</li>
    <li><strong>Status:</strong> Draft or Published</li>
    <li><strong>Publish date:</strong> Controls the display order on the insights page</li>
</ul>

<!-- 8. Team -->
<h2 id="team">8. Team Members</h2>
<p>Go to <strong>Content &rarr; Team</strong> to manage team member profiles.</p>
<ul>
    <li><strong>Photo:</strong> Upload via the form (JPG/PNG/WEBP)</li>
    <li><strong>Bio:</strong> English and German versions</li>
    <li><strong>Title:</strong> English and German (e.g. "Director, Portfolio Manager" / "Direktor, Portfoliomanager")</li>
    <li><strong>LinkedIn URL &amp; Email:</strong> Shown on the team page</li>
    <li><strong>Display order:</strong> Controls the sequence on the page</li>
</ul>

<!-- 9. Funds -->
<h2 id="funds">9. Funds &amp; Share Classes</h2>
<p>Go to <strong>Funds &rarr; Funds &amp; Share Classes</strong>.</p>
<p>This page manages the two funds (Mori Eastern European Fund, Mori Ottoman Fund) and their share classes.</p>
<ul>
    <li><strong>Edit fund:</strong> Click the pencil icon &rarr; change name, description (EN/DE), cover image, status</li>
    <li><strong>Share classes:</strong> Each fund has multiple share classes (e.g. EE Class A EUR, Otto Class B USD). Edit ISIN, currency, launch date</li>
    <li><strong>Add share class:</strong> Click "Add share class" and fill the modal form</li>
</ul>
<div class="tip"><strong>Note:</strong> Fund names appear automatically in the website header, footer, and fund cards — no need to update them in multiple places.</div>

<!-- 10. Performance -->
<h2 id="performance">10. Performance (NAV)</h2>
<p>Go to <strong>Funds &rarr; Performance (NAV)</strong> to add daily NAV entries for each share class.</p>
<ul>
    <li>Select a <strong>fund</strong> and <strong>share class</strong></li>
    <li>Enter the <strong>date</strong> and <strong>NAV value</strong></li>
    <li>Click <strong>Add entry</strong></li>
</ul>
<p>The Performance page on the website displays an interactive ApexChart generated from this data, plus cumulative returns (YTD, 1Y, 3Y, 5Y, 10Y).</p>

<!-- 11. Documents -->
<h2 id="documents">11. Documents (FundHub)</h2>
<p>Go to <strong>Funds &rarr; Documents</strong> to manage the FundHub document repository.</p>
<ul>
    <li><strong>Upload PDFs:</strong> Click "Add document" &rarr; select file, fund, share class(es), document type, title</li>
    <li><strong>Document types:</strong> Prospectus, KIID, PRIIPs KID, Annual/Semi-Annual Accounts, Factsheet, Marketing, Other</li>
    <li><strong>Locale:</strong> EN or DE — determines which language version appears</li>
    <li><strong>Matrix view:</strong> The FundHub page shows documents in a share-class × type grid</li>
</ul>

<!-- 12. Media -->
<h2 id="media">12. Media Library</h2>
<p>Go to <strong>Content &rarr; Media Library</strong> to manage all uploaded files.</p>
<ul>
    <li><strong>Upload:</strong> JPG, PNG, GIF, WEBP images and MP4/WebM videos</li>
    <li><strong>Folder:</strong> Optionally tag uploads by folder (hero, team, insights, etc.)</li>
    <li><strong>Delete:</strong> Removes the file from the server and database</li>
</ul>
<p>Uploaded files are stored in <code>/uploads/media/YEAR/</code>.</p>

<!-- 13. Messages -->
<h2 id="messages">13. Messages (Contact Inbox)</h2>
<p>Go to <strong>Engagement &rarr; Messages</strong> to see messages submitted via the contact form.</p>
<ul>
    <li>View sender name, email, subject, message, and submission date</li>
    <li><strong>Reply:</strong> Click the reply button to send a branded email response directly from the admin panel</li>
    <li>Replies are sent via SMTP (configure in Settings)</li>
</ul>

<!-- 14. Newsletter -->
<h2 id="newsletter">14. Newsletter</h2>
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

<!-- 15. Settings -->
<h2 id="settings">15. Settings</h2>
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

<!-- 16. SEO -->
<h2 id="seo">16. SEO &amp; Custom Code</h2>
<p>Go to <strong>System &rarr; SEO &amp; Code</strong> to manage advanced SEO settings and code injection.</p>
<ul>
    <li><strong>Custom &lt;head&gt; code:</strong> Paste Google Tag Manager, Meta Pixel, or any script that goes in the HTML &lt;head&gt;</li>
    <li><strong>Custom footer code:</strong> Paste scripts that go before &lt;/body&gt;</li>
    <li><strong>Robots.txt:</strong> Managed automatically — blocks admin, API, source, and database directories</li>
    <li><strong>Sitemap:</strong> Auto-generated at <code>/sitemap.xml</code> with all published pages and insights</li>
</ul>

<!-- 17. Users -->
<h2 id="users">17. Users</h2>
<p>Go to <strong>System &rarr; Users</strong> to manage admin panel users.</p>
<ul>
    <li><strong>Roles:</strong> <code>super_admin</code> (full access) or <code>editor</code> (content only)</li>
    <li><strong>Create user:</strong> Email + password (min 12 chars)</li>
    <li><strong>Change password:</strong> Enter a new password in the edit modal</li>
    <li><strong>Delete:</strong> Remove a user permanently</li>
</ul>
<div class="warn"><strong>Important:</strong> Only super_admin users can access Settings, Users, SEO, and Database pages.</div>

<!-- 18. Audit -->
<h2 id="audit">18. Audit Log</h2>
<p>Go to <strong>System &rarr; Audit Log</strong> to see a complete history of all actions taken in the admin panel.</p>
<ul>
    <li>Every create, update, delete, login, and settings change is recorded</li>
    <li><strong>Search:</strong> Filter by text, action type, or date range</li>
    <li>Shows who did what, when, and on which record</li>
</ul>

<!-- 19. Database -->
<h2 id="database">19. Database &amp; Migrations</h2>
<p>Go to <strong>System &rarr; Database</strong> to manage the database schema.</p>
<ul>
    <li><strong>Schema sync:</strong> Re-runs the base schema (safe — uses CREATE TABLE IF NOT EXISTS)</li>
    <li><strong>Migrations:</strong> Shows pending and applied migration files. Click "Run" to apply pending migrations</li>
</ul>
<div class="warn"><strong>Note:</strong> This is an advanced feature. Normally, running <code>install.php</code> handles everything automatically.</div>

<!-- 20. Bilingual -->
<h2 id="bilingual">20. Bilingual Content (EN/DE)</h2>
<p>The site supports English and German. Here's how bilingual content works:</p>
<ul>
    <li><strong>Language switcher:</strong> Visitors click EN/DE in the topbar (desktop) or mobile menu to switch</li>
    <li><strong>Pages:</strong> Create separate EN and DE versions with the same slug</li>
    <li><strong>Funds &amp; Team:</strong> Each record has <code>name_en</code>, <code>name_de</code>, <code>description_en</code>, <code>description_de</code> fields</li>
    <li><strong>Homepage text:</strong> The Homepage Content page has separate EN and DE fields for descriptions</li>
    <li><strong>UI labels:</strong> Managed in <code>src/lang/en.php</code> and <code>src/lang/de.php</code> — these are the menu labels, button text, etc.</li>
</ul>

<!-- 21. Shortcuts -->
<h2 id="shortcuts">21. Keyboard Shortcuts</h2>
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
