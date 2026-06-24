<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\format_date;
use function Mori\format_bytes;
use function Mori\t;

$funds = $shareClasses = $latestByScType = $latestByFundType = $latestGlobal = [];

$curLocale = I18n::locale();

try {
    $db = Database::instance();
    $funds        = $db->fetchAll('SELECT * FROM funds WHERE status = "active" ORDER BY display_order');
    $shareClasses = $db->fetchAll('SELECT sc.*, f.name_en AS fund_name_en, f.name_de AS fund_name_de, f.slug AS fund_slug
                                     FROM share_classes sc
                                     JOIN funds f ON f.id = sc.fund_id
                                    WHERE sc.status = "active"
                                    ORDER BY f.display_order, sc.display_order');

    // Filter documents by current locale OR 'any' — fix for EN→DE doc mismatch
    $rows = $db->fetchAll(
        'SELECT d.id, d.document_type, d.title, d.file_path, d.file_name, d.file_size,
                d.document_date, d.fund_id, d.locale, dsc.share_class_id, d.created_at
           FROM documents d
           LEFT JOIN document_share_classes dsc ON dsc.document_id = d.id
          WHERE (d.category IS NULL OR d.category = "share_class" OR d.category = "")
            AND (d.locale = :loc OR d.locale = "any")
          ORDER BY COALESCE(d.document_date, d.created_at) DESC, d.created_at DESC',
        ['loc' => $curLocale]
    );

    foreach ($rows as $r) {
        $type = $r['document_type'];
        if (!empty($r['share_class_id'])) {
            $k = $r['share_class_id'] . '|' . $type;
            if (!isset($latestByScType[$k])) $latestByScType[$k] = $r;
        } elseif (!empty($r['fund_id'])) {
            $k = $r['fund_id'] . '|' . $type;
            if (!isset($latestByFundType[$k])) $latestByFundType[$k] = $r;
        } else {
            if (!isset($latestGlobal[$type])) $latestGlobal[$type] = $r;
        }
    }
} catch (\Throwable) {
    // DB unreachable → empty arrays already initialised
}

function fundhub_doc_for(array $sc, string $type, array $latestByScType, array $latestByFundType, array $latestGlobal): ?array {
    $k = $sc['id'] . '|' . $type;
    if (isset($latestByScType[$k]))   return $latestByScType[$k];
    $k = $sc['fund_id'] . '|' . $type;
    if (isset($latestByFundType[$k])) return $latestByFundType[$k];
    return $latestGlobal[$type] ?? null;
}

$columns = [
    'prospectus'   => t('doc.prospectus'),
    'kiid'         => t('doc.kiid'),
    'priips'       => t('doc.priips'),
    'annual'       => t('doc.annual'),
    'semi_annual'  => t('doc.semi_annual'),
    'factsheet'    => t('doc.factsheet'),
];
$freqs = [
    'prospectus'   => t('doc.freq.annually'),
    'kiid'         => t('doc.freq.annually'),
    'priips'       => t('doc.freq.annually'),
    'annual'       => t('doc.freq.annually'),
    'semi_annual'  => t('doc.freq.annually'),
    'factsheet'    => t('doc.freq.quarterly'),
];

$page = [
    'title'       => t('doc.fundhub.title') . ' · Mori Capital',
    'description' => t('doc.fundhub.desc'),
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.documents')],
        ['label' => t('doc.share_class_docs')],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<div class="our-services" style="padding:50px 0;">
    <div class="container">
        <!-- Intro -->
        <div style="margin-bottom:24px;">
            <span style="font-size:12px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:var(--accent-color,#1ABC9C);"><?= e(t('doc.fundhub.eyebrow')) ?></span>
            <h2 style="font-size:clamp(24px,2.6vw,32px);margin:6px 0 8px;"><?= e(t('doc.share_class_docs_title')) ?></h2>
            <p style="font-size:14.5px;color:var(--mori-text-soft,#5A6B7B);line-height:1.6;margin:0;max-width:780px;">
                <?= e(t('doc.share_class_intro')) ?>
            </p>
        </div>

        <?php if (empty($shareClasses)): ?>
            <div style="background:#fff;border:1px dashed var(--mori-border,#E1E7EE);border-radius:10px;padding:48px;text-align:center;color:var(--mori-muted,#7A8B99);font-size:14px;">
                <?= e(t('doc.no_share_classes')) ?>
            </div>
        <?php else: ?>
        <div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;overflow:auto;">
            <table class="fundhub-matrix" style="width:100%;border-collapse:collapse;font-size:13px;min-width:1100px;">
                <thead>
                    <tr style="background:var(--mori-bg-soft,#F5F7FA);">
                        <th style="text-align:left;padding:14px 16px;font-size:10.5px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:700;position:sticky;left:0;background:var(--mori-bg-soft,#F5F7FA);z-index:2;min-width:130px;">ISIN</th>
                        <th style="text-align:left;padding:14px 16px;font-size:10.5px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:700;min-width:160px;"><?= e(t('doc.share_class')) ?></th>
                        <?php foreach ($columns as $key => $label): ?>
                        <th style="text-align:center;padding:14px 12px;font-size:10.5px;text-transform:uppercase;letter-spacing:0.1em;color:var(--mori-muted,#7A8B99);font-weight:700;min-width:110px;">
                            <?= e($label) ?>
                            <div style="font-size:9px;font-weight:500;color:var(--mori-muted,#7A8B99);opacity:.7;margin-top:2px;text-transform:none;letter-spacing:0;"><?= e($freqs[$key]) ?></div>
                        </th>
                        <?php endforeach; ?>
                        <th style="text-align:center;padding:14px 12px;font-size:10.5px;text-transform:uppercase;letter-spacing:0.1em;color:var(--mori-muted,#7A8B99);font-weight:700;min-width:140px;">
                            <?= e(t('doc.performance')) ?>
                            <div style="font-size:9px;font-weight:500;color:var(--mori-muted,#7A8B99);opacity:.7;margin-top:2px;text-transform:none;letter-spacing:0;"><?= e(t('doc.freq.monthly')) ?></div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php $currentFundId = null; foreach ($shareClasses as $sc):
                        $fundName = I18n::fieldFor($sc, 'fund_name');
                    ?>
                        <?php if ($currentFundId !== $sc['fund_id']): $currentFundId = $sc['fund_id']; ?>
                        <tr>
                            <td colspan="<?= count($columns) + 3 ?>" style="padding:14px 16px;background:var(--mori-bg-tint,#EEF3F8);font-size:11px;text-transform:uppercase;letter-spacing:0.14em;font-weight:700;color:var(--primary-color,#1B3A5C);border-top:1px solid var(--mori-border,#E1E7EE);position:sticky;left:0;z-index:1;">
                                <?= e(strtoupper($fundName)) ?> <span style="color:var(--mori-muted,#7A8B99);font-weight:500;text-transform:none;letter-spacing:0;">· Mori Umbrella Fund plc</span>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr style="border-top:1px solid var(--mori-border,#E1E7EE);">
                            <td style="padding:13px 16px;font-family:monospace;font-size:12px;color:var(--mori-text-soft,#5A6B7B);position:sticky;left:0;background:#fff;z-index:1;">
                                <?= e($sc['isin'] ?? '—') ?>
                            </td>
                            <td style="padding:13px 16px;font-weight:600;color:var(--primary-color,#1B3A5C);">
                                <?= e($sc['name']) ?>
                                <div style="font-size:11px;font-weight:500;color:var(--mori-muted,#7A8B99);margin-top:2px;"><?= e($sc['currency']) ?></div>
                            </td>
                            <?php foreach ($columns as $type => $label):
                                $d = fundhub_doc_for($sc, $type, $latestByScType, $latestByFundType, $latestGlobal);
                            ?>
                            <td style="padding:13px 12px;text-align:center;">
                                <?php if ($d): ?>
                                <a href="<?= asset('api/download.php?id=' . (int)$d['id']) ?>" target="_blank" rel="noopener noreferrer" title="<?= e($d['title']) ?> · <?= e(format_date($d['document_date'])) ?> · <?= e(format_bytes((int)$d['file_size'])) ?>" style="display:inline-flex;align-items:center;gap:4px;color:var(--accent-color,#1ABC9C);font-weight:600;text-decoration:none;font-size:12.5px;">
                                    <i class="fa-regular fa-file-pdf"></i> PDF
                                </a>
                                <?php else: ?>
                                <span style="color:var(--mori-muted,#7A8B99);font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                            <td style="padding:13px 12px;text-align:center;">
                                <a href="<?= asset('fund-performance.php?fund=' . (int)$sc['fund_id'] . '&class=' . (int)$sc['id']) ?>" style="display:inline-flex;align-items:center;gap:4px;color:var(--accent-color,#1ABC9C);font-weight:600;text-decoration:none;font-size:12.5px;">
                                    <i class="fa-solid fa-chart-line"></i> <?= e(t('doc.view_chart')) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:18px;font-size:11.5px;color:var(--mori-muted,#7A8B99);line-height:1.6;">
            <strong style="color:var(--primary-color,#1B3A5C);"><?= e(t('doc.notes')) ?>:</strong>
            <?= e(t('doc.matrix_notes')) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
?>
