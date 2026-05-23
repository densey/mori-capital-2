<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\format_date;
use function Mori\format_bytes;
use function Mori\t;

$funds = $shareClasses = $documents = $latestByScType = $latestByFundType = $latestGlobal = [];

try {
    $db = Database::instance();
    $funds        = $db->fetchAll('SELECT * FROM funds WHERE status = "active" ORDER BY display_order');
    $shareClasses = $db->fetchAll('SELECT sc.*, f.name_en AS fund_name, f.slug AS fund_slug
                                     FROM share_classes sc
                                     JOIN funds f ON f.id = sc.fund_id
                                    WHERE sc.status = "active"
                                    ORDER BY f.display_order, sc.display_order');

    // === Resolve documents into 3 buckets ===
    // 1) per share-class:  document_share_classes link table → newest per (share_class, type)
    // 2) per fund:         documents.fund_id set, no share-class link → newest per (fund, type)
    // 3) global / umbrella: no fund_id and no share-class link → newest per type
    $rows = $db->fetchAll(
        'SELECT d.id, d.document_type, d.title, d.file_path, d.file_name, d.file_size,
                d.document_date, d.fund_id, dsc.share_class_id, d.created_at
           FROM documents d
           LEFT JOIN document_share_classes dsc ON dsc.document_id = d.id
          ORDER BY COALESCE(d.document_date, d.created_at) DESC, d.created_at DESC'
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

    // Flat list (used by the alternative ?view=list and admin redirect)
    $documents = $db->fetchAll(
        'SELECT d.*, f.name_en AS fund_name
           FROM documents d
           LEFT JOIN funds f ON f.id = d.fund_id
          ORDER BY COALESCE(d.document_date, d.created_at) DESC'
    );
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
    'prospectus'   => 'Prospectus',
    'kiid'         => 'KIID',
    'priips'       => 'PRIIPs',
    'annual'       => 'Audited Accounts',
    'semi_annual'  => 'Semi-Annual Accounts',
    'factsheet'    => 'Factsheet',
];
$freqs = [
    'prospectus'   => 'Annually',
    'kiid'         => 'Annually',
    'priips'       => 'Annually',
    'annual'       => 'Annually',
    'semi_annual'  => 'Annually',
    'factsheet'    => 'Quarterly',
];

$view = $_GET['view'] ?? 'matrix';

$page = [
    'title'       => 'FundHub — Document Repository · Mori Capital',
    'description' => 'Share-class document repository: Prospectus, KIIDs, PRIIPs KIDs, Audited Accounts, Semi-Annual Accounts, Factsheets and per-class performance.',
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => 'FundHub'],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<div class="our-services" style="padding:50px 0;">
    <div class="container">

        <!-- Intro + view toggle -->
        <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:18px;margin-bottom:24px;">
            <div style="max-width:780px;">
                <span style="font-size:12px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:var(--accent-color,#1ABC9C);">FundHub</span>
                <h2 style="font-size:clamp(24px,2.6vw,32px);margin:6px 0 8px;">Share-class document repository</h2>
                <p style="font-size:14.5px;color:var(--mori-text-soft,#5A6B7B);line-height:1.6;margin:0;">
                    Pick any share class to access the Prospectus, KIID, PRIIPs KID, accounts, factsheets and a per-class performance chart. Sub-fund of <strong>Mori Umbrella Fund plc</strong>.
                </p>
            </div>
            <div style="display:inline-flex;background:var(--mori-bg-soft,#F5F7FA);border:1px solid var(--mori-border,#E1E7EE);border-radius:999px;padding:3px;font-size:12px;font-weight:600;">
                <a href="?view=matrix" style="padding:7px 16px;border-radius:999px;text-decoration:none;<?= $view==='matrix' ? 'background:var(--accent-color,#1ABC9C);color:#fff;' : 'color:var(--mori-text-soft,#5A6B7B);' ?>"><i class="fa-solid fa-table-cells"></i> Matrix</a>
                <a href="?view=list"   style="padding:7px 16px;border-radius:999px;text-decoration:none;<?= $view==='list'   ? 'background:var(--accent-color,#1ABC9C);color:#fff;' : 'color:var(--mori-text-soft,#5A6B7B);' ?>"><i class="fa-solid fa-list"></i> List</a>
            </div>
        </div>

<?php if ($view === 'matrix'): ?>

        <!-- MATRIX VIEW (per Desmond's FundHub_Sample spec) -->
        <?php if (empty($shareClasses)): ?>
            <div style="background:#fff;border:1px dashed var(--mori-border,#E1E7EE);border-radius:10px;padding:48px;text-align:center;color:var(--mori-muted,#7A8B99);font-size:14px;">
                No share classes configured yet.<?php if (\Mori\Auth::check()): ?> Add them from the <a href="<?= asset('admin/funds.php') ?>" style="color:var(--accent-color,#1ABC9C);">admin panel</a>.<?php endif; ?>
            </div>
        <?php else: ?>
        <div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;overflow:auto;">
            <table class="fundhub-matrix" style="width:100%;border-collapse:collapse;font-size:13px;min-width:1100px;">
                <thead>
                    <tr style="background:var(--mori-bg-soft,#F5F7FA);">
                        <th style="text-align:left;padding:14px 16px;font-size:10.5px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:700;position:sticky;left:0;background:var(--mori-bg-soft,#F5F7FA);z-index:2;min-width:130px;">ISIN</th>
                        <th style="text-align:left;padding:14px 16px;font-size:10.5px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:700;min-width:160px;">Share class</th>
                        <?php foreach ($columns as $key => $label): ?>
                        <th style="text-align:center;padding:14px 12px;font-size:10.5px;text-transform:uppercase;letter-spacing:0.1em;color:var(--mori-muted,#7A8B99);font-weight:700;min-width:110px;">
                            <?= e($label) ?>
                            <div style="font-size:9px;font-weight:500;color:var(--mori-muted,#7A8B99);opacity:.7;margin-top:2px;text-transform:none;letter-spacing:0;"><?= e($freqs[$key]) ?></div>
                        </th>
                        <?php endforeach; ?>
                        <th style="text-align:center;padding:14px 12px;font-size:10.5px;text-transform:uppercase;letter-spacing:0.1em;color:var(--mori-muted,#7A8B99);font-weight:700;min-width:140px;">
                            Performance
                            <div style="font-size:9px;font-weight:500;color:var(--mori-muted,#7A8B99);opacity:.7;margin-top:2px;text-transform:none;letter-spacing:0;">Monthly</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php $currentFundId = null; foreach ($shareClasses as $sc): ?>
                        <?php if ($currentFundId !== $sc['fund_id']): $currentFundId = $sc['fund_id']; ?>
                        <tr>
                            <td colspan="<?= count($columns) + 3 ?>" style="padding:14px 16px;background:var(--mori-bg-tint,#EEF3F8);font-size:11px;text-transform:uppercase;letter-spacing:0.14em;font-weight:700;color:var(--primary-color,#1B3A5C);border-top:1px solid var(--mori-border,#E1E7EE);position:sticky;left:0;z-index:1;">
                                <?= e(strtoupper($sc['fund_name'])) ?> <span style="color:var(--mori-muted,#7A8B99);font-weight:500;text-transform:none;letter-spacing:0;">· Mori Umbrella Fund plc</span>
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
                                    <i class="fa-solid fa-chart-line"></i> View chart
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Legend -->
        <div style="margin-top:18px;font-size:11.5px;color:var(--mori-muted,#7A8B99);line-height:1.6;">
            <strong style="color:var(--primary-color,#1B3A5C);">Notes:</strong>
            Prospectus, Audited Accounts and Semi-Annual Accounts apply to all share classes of the umbrella. KIID, PRIIPs KID and Performance are per share class. Factsheet is one per fund. PDF links open the latest version on file; previous versions are available on request.
        </div>
        <?php endif; ?>

<?php else: ?>

        <!-- LIST VIEW (alternative — all documents flat, filterable) -->
        <?php
        $filterFund = $_GET['fund'] ?? '';
        $filterType = $_GET['type'] ?? '';
        $filterQ    = $_GET['q'] ?? '';
        $filteredDocs = array_filter($documents, function ($d) use ($filterFund, $filterType, $filterQ, $funds) {
            if ($filterFund !== '') {
                $f = null;
                foreach ($funds as $ff) if ($ff['slug'] === $filterFund) { $f = $ff; break; }
                if (!$f || (int)$d['fund_id'] !== (int)$f['id']) return false;
            }
            if ($filterType !== '' && $d['document_type'] !== $filterType) return false;
            if ($filterQ !== '' && stripos($d['title'], $filterQ) === false) return false;
            return true;
        });
        $types = $columns + ['marketing' => 'Marketing', 'other' => 'Other'];
        ?>
        <form method="get" style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;padding:18px 22px;margin-bottom:24px;display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:12px;align-items:end;">
            <input type="hidden" name="view" value="list">
            <div>
                <label style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;">Fund</label>
                <select name="fund" style="width:100%;padding:9px 12px;border:1px solid var(--mori-border,#E1E7EE);border-radius:6px;font-family:inherit;font-size:13.5px;">
                    <option value="">All funds</option>
                    <?php foreach ($funds as $f): ?>
                    <option value="<?= e($f['slug']) ?>" <?= $filterFund===$f['slug']?'selected':'' ?>><?= e($f['name_en']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;">Document type</label>
                <select name="type" style="width:100%;padding:9px 12px;border:1px solid var(--mori-border,#E1E7EE);border-radius:6px;font-family:inherit;font-size:13.5px;">
                    <option value="">All types</option>
                    <?php foreach ($types as $k => $lbl): ?>
                    <option value="<?= e($k) ?>" <?= $filterType===$k?'selected':'' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;">Search</label>
                <input type="text" name="q" placeholder="Search by title…" value="<?= e($filterQ) ?>" style="width:100%;padding:9px 12px;border:1px solid var(--mori-border,#E1E7EE);border-radius:6px;font-family:inherit;font-size:13.5px;">
            </div>
            <button type="submit" style="background:var(--accent-color,#1ABC9C);color:#fff;border:none;padding:10px 20px;border-radius:6px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;">Filter</button>
        </form>

        <?php if (empty($filteredDocs)): ?>
            <div style="background:#fff;border:1px dashed var(--mori-border,#E1E7EE);border-radius:10px;padding:48px;text-align:center;color:var(--mori-muted,#7A8B99);font-size:14px;">
                No documents available yet.<?php if (\Mori\Auth::check()): ?> Upload from the <a href="<?= asset('admin/documents.php') ?>" style="color:var(--accent-color,#1ABC9C);">admin panel</a>.<?php endif; ?>
            </div>
        <?php else: ?>
            <div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;overflow:hidden;">
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <thead>
                        <tr style="background:var(--mori-bg-soft,#F5F7FA);">
                            <th style="text-align:left;padding:14px 18px;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;">Document</th>
                            <th style="text-align:left;padding:14px 18px;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;">Fund</th>
                            <th style="text-align:left;padding:14px 18px;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;">Type</th>
                            <th style="text-align:left;padding:14px 18px;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;">Date</th>
                            <th style="text-align:right;padding:14px 18px;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;">Size</th>
                            <th style="text-align:right;padding:14px 18px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredDocs as $doc): ?>
                        <tr style="border-top:1px solid var(--mori-border,#E1E7EE);">
                            <td style="padding:14px 18px;">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <i class="fa-regular fa-file-pdf" style="color:var(--accent-color,#1ABC9C);font-size:18px;"></i>
                                    <span style="font-weight:600;color:var(--primary-color,#1B3A5C);"><?= e($doc['title']) ?></span>
                                </div>
                            </td>
                            <td style="padding:14px 18px;color:var(--mori-text-soft,#5A6B7B);"><?= e($doc['fund_name'] ?? '— umbrella —') ?></td>
                            <td style="padding:14px 18px;color:var(--mori-text-soft,#5A6B7B);"><?= e($types[$doc['document_type']] ?? $doc['document_type']) ?></td>
                            <td style="padding:14px 18px;color:var(--mori-text-soft,#5A6B7B);"><?= e(format_date($doc['document_date'])) ?></td>
                            <td style="padding:14px 18px;text-align:right;color:var(--mori-muted,#7A8B99);font-size:12px;"><?= e(format_bytes((int)$doc['file_size'])) ?></td>
                            <td style="padding:14px 18px;text-align:right;">
                                <a href="<?= asset('api/download.php?id=' . (int)$doc['id']) ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:6px;background:var(--accent-color,#1ABC9C);color:#fff;padding:8px 14px;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;">
                                    <i class="fa-solid fa-download"></i> Download
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

<?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
