<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\t;

$grouped = [];
try {
    $rows = Database::instance()->fetchAll(
        'SELECT d.* FROM documents d
          WHERE d.category = "other"
            AND (d.locale = :loc OR d.locale = "any")
            AND COALESCE(d.display_year, YEAR(d.document_date), YEAR(d.created_at)) >= 2024
          ORDER BY COALESCE(d.display_year, YEAR(d.document_date), YEAR(d.created_at)) DESC,
                   d.display_order ASC,
                   COALESCE(d.document_date, d.created_at) DESC',
        ['loc' => I18n::locale()]
    );
    foreach ($rows as $d) {
        $y = $d['display_year'] ?: (int)date('Y', strtotime($d['document_date'] ?: $d['created_at']));
        if ($y < 2024) continue;
        $grouped[$y][] = $d;
    }
    krsort($grouped);
} catch (\Throwable) {}

$page = [
    'title'       => t('doc.other_documents') . ' · Mori Capital',
    'description' => t('doc.other.intro'),
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.documents')],
        ['label' => t('doc.other_documents')],
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
            <h2 style="font-size:clamp(24px,2.6vw,32px);margin:6px 0 8px;"><?= e(t('doc.other_documents')) ?></h2>
            <p style="font-size:14.5px;color:var(--mori-text-soft,#5A6B7B);line-height:1.6;margin:0;"><?= e(t('doc.other.intro')) ?></p>
        </div>

        <?php if (empty($grouped)):
            $docs = [];
            $emptyKey = 'doc.no_other';
            $headerLabel = null;
            include __DIR__ . '/src/partials/doc-list-table.php';
        else: foreach ($grouped as $year => $docs): ?>
        <div style="margin-bottom:28px;">
            <h3 style="font-size:18px;color:var(--primary-color,#1B3A5C);margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid var(--accent-color,#1ABC9C);display:inline-block;padding-right:18px;"><?= e($year) ?></h3>
            <?php
                $emptyKey = 'doc.no_other';
                $headerLabel = null;
                include __DIR__ . '/src/partials/doc-list-table.php';
            ?>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
?>
