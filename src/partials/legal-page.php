<?php
/**
 * Shared layout for legal-style pages: pulls a page from DB by slug.
 * Expects: $slug, $defaultTitle
 */
use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\t;

$db = Database::instance();
try {
    $pageData = $db->fetchOne(
        'SELECT * FROM pages WHERE slug = :s AND locale = :loc AND status = "published"',
        ['s' => $slug, 'loc' => I18n::locale()]
    ) ?? $db->fetchOne('SELECT * FROM pages WHERE slug = :s AND locale = "en"', ['s' => $slug]);
} catch (\Throwable) { $pageData = null; }

$page = [
    'title'       => $pageData['meta_title'] ?? $defaultTitle,
    'description' => $pageData['meta_description'] ?? '',
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => $pageData['title'] ?? $defaultTitle],
    ],
];

include __DIR__ . '/head.php';
include __DIR__ . '/topbar.php';
include __DIR__ . '/header.php';
include __DIR__ . '/page-header.php';
?>

<div class="our-services" style="padding:70px 0;">
    <div class="container">
        <div class="row">
            <div class="col-lg-9 mx-auto">
                <div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:14px;padding:50px 60px;">
                    <article class="legal-content" style="font-size:15px;line-height:1.75;color:var(--mori-text-soft,#5A6B7B);">
                        <?= $pageData['body'] ?? '<p>Content not yet published. Add it from the admin panel.</p>' ?>
                    </article>
                    <div style="margin-top:30px;padding-top:18px;border-top:1px solid var(--mori-border,#E1E7EE);font-size:12px;color:var(--mori-muted,#7A8B99);">
                        Last updated: <?= e(\Mori\format_date($pageData['updated_at'] ?? null) ?: '—') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.legal-content h2 { color: var(--primary-color, #1B3A5C); font-size: 22px; margin: 28px 0 12px; }
.legal-content h3 { color: var(--primary-color, #1B3A5C); font-size: 17px; margin: 22px 0 10px; }
.legal-content p { margin-bottom: 14px; }
.legal-content ul { padding-left: 24px; margin-bottom: 16px; }
.legal-content li { margin-bottom: 6px; }
.legal-content a { color: var(--accent-color, #1ABC9C); }
.legal-content strong { color: var(--primary-color, #1B3A5C); }
</style>

<?php
include __DIR__ . '/footer.php';
include __DIR__ . '/scripts.php';
