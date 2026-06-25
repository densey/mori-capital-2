<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\t;

$docs = [];
try {
    try {
        $docs = Database::instance()->fetchAll(
            'SELECT d.* FROM documents d
              WHERE d.category = "company_policy"
                AND (d.locale = :loc OR d.locale = "any")
              ORDER BY d.display_order ASC, d.title ASC',
            ['loc' => I18n::locale()]
        );
    } catch (\Throwable) {
        // Fallback if migration hasn't been applied yet
        $docs = Database::instance()->fetchAll(
            'SELECT d.* FROM documents d
              WHERE d.category = "company_policy"
                AND (d.locale = :loc OR d.locale = "any")
              ORDER BY d.title ASC',
            ['loc' => I18n::locale()]
        );
    }
} catch (\Throwable) {}

$page = [
    'title'       => t('doc.company_policies') . ' · Mori Capital',
    'description' => t('doc.policies.intro'),
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.documents')],
        ['label' => t('doc.company_policies')],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<div class="our-services" style="padding:50px 0;">
    <div class="container">
        <div style="margin-bottom:24px;max-width:780px;">
            <span style="font-size:12px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:var(--accent-color,#1ABC9C);"><?= e(t('nav.documents')) ?></span>
            <h2 style="font-size:clamp(24px,2.6vw,32px);margin:6px 0 8px;"><?= e(t('doc.company_policies')) ?></h2>
            <p style="font-size:14.5px;color:var(--mori-text-soft,#5A6B7B);line-height:1.6;margin:0;"><?= e(t('doc.policies.intro')) ?></p>
        </div>
        <?php
        $emptyKey    = 'doc.no_policies';
        $headerLabel = t('doc.policy');
        include __DIR__ . '/src/partials/doc-list-table.php';
        ?>
    </div>
</div>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
?>
