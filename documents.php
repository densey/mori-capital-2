<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use function Mori\e;
use function Mori\asset;
use function Mori\format_date;
use function Mori\format_bytes;
use function Mori\t;

try {
    $db = Database::instance();
    $funds = $db->fetchAll('SELECT * FROM funds WHERE status="active" ORDER BY display_order');

    // Filters
    $where = ['1=1'];
    $params = [];
    if (!empty($_GET['fund'])) {
        $fundSlug = (string)$_GET['fund'];
        $f = $db->fetchOne('SELECT id FROM funds WHERE slug=:s', ['s' => $fundSlug]);
        if ($f) { $where[] = 'd.fund_id = :fid'; $params['fid'] = (int)$f['id']; }
    }
    if (!empty($_GET['type'])) {
        $where[] = 'd.document_type = :tp'; $params['tp'] = (string)$_GET['type'];
    }
    if (!empty($_GET['q'])) {
        $where[] = 'd.title LIKE :q'; $params['q'] = '%' . $_GET['q'] . '%';
    }
    $documents = $db->fetchAll(
        'SELECT d.*, f.name_en AS fund_name FROM documents d
           LEFT JOIN funds f ON f.id = d.fund_id
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY d.document_date DESC, d.created_at DESC',
        $params
    );
} catch (\Throwable) { $funds = []; $documents = []; }

$types = [
    'prospectus'   => 'Prospectus',
    'kiid'         => 'KIID',
    'priips'       => 'PRIIPs KID',
    'annual'       => 'Annual Report',
    'semi_annual'  => 'Semi-Annual Report',
    'factsheet'    => 'Factsheet',
    'marketing'    => 'Marketing',
    'other'        => 'Other',
];

$page = [
    'title'       => 'Document Hub — Mori Capital',
    'description' => 'Fund documentation: prospectus, KIIDs, PRIIPs KIDs, annual and semi-annual reports, factsheets.',
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => 'Document Hub'],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<div class="our-services" style="padding:60px 0;">
    <div class="container">

        <!-- Filter bar -->
        <form method="get" style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;padding:20px 24px;margin-bottom:24px;display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:14px;align-items:end;">
            <div>
                <label style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;">Fund</label>
                <select name="fund" style="width:100%;padding:10px 12px;border:1px solid var(--mori-border,#E1E7EE);border-radius:6px;font-family:inherit;font-size:14px;">
                    <option value="">All funds</option>
                    <?php foreach ($funds as $f): ?>
                    <option value="<?= e($f['slug']) ?>" <?= ($_GET['fund'] ?? '')===$f['slug']?'selected':'' ?>><?= e($f['name_en']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;">Document type</label>
                <select name="type" style="width:100%;padding:10px 12px;border:1px solid var(--mori-border,#E1E7EE);border-radius:6px;font-family:inherit;font-size:14px;">
                    <option value="">All types</option>
                    <?php foreach ($types as $k => $lbl): ?>
                    <option value="<?= e($k) ?>" <?= ($_GET['type'] ?? '')===$k?'selected':'' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;">Search</label>
                <input type="text" name="q" placeholder="Search by title…" value="<?= e($_GET['q'] ?? '') ?>" style="width:100%;padding:10px 12px;border:1px solid var(--mori-border,#E1E7EE);border-radius:6px;font-family:inherit;font-size:14px;">
            </div>
            <button type="submit" style="background:var(--accent-color,#1ABC9C);color:#fff;border:none;padding:11px 22px;border-radius:6px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;">Filter</button>
        </form>

        <!-- Results -->
        <?php if (empty($documents)): ?>
            <div style="background:#fff;border:1px dashed var(--mori-border,#E1E7EE);border-radius:10px;padding:48px;text-align:center;color:var(--mori-muted,#7A8B99);font-size:14px;">
                No documents available yet. Upload from the <a href="<?= asset('admin/documents.php') ?>" style="color:var(--accent-color,#1ABC9C);">admin panel</a>.
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
                        <?php foreach ($documents as $doc): ?>
                        <tr style="border-top:1px solid var(--mori-border,#E1E7EE);">
                            <td style="padding:14px 18px;">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <i class="fa-regular fa-file-pdf" style="color:var(--accent-color,#1ABC9C);font-size:18px;"></i>
                                    <span style="font-weight:600;color:var(--primary-color,#1B3A5C);"><?= e($doc['title']) ?></span>
                                </div>
                            </td>
                            <td style="padding:14px 18px;color:var(--mori-text-soft,#5A6B7B);"><?= e($doc['fund_name'] ?? '—') ?></td>
                            <td style="padding:14px 18px;color:var(--mori-text-soft,#5A6B7B);"><?= e($types[$doc['document_type']] ?? $doc['document_type']) ?></td>
                            <td style="padding:14px 18px;color:var(--mori-text-soft,#5A6B7B);"><?= e(format_date($doc['document_date'])) ?></td>
                            <td style="padding:14px 18px;text-align:right;color:var(--mori-muted,#7A8B99);font-size:12px;"><?= e(format_bytes((int)$doc['file_size'])) ?></td>
                            <td style="padding:14px 18px;text-align:right;">
                                <a href="<?= asset('api/download.php?id=' . (int)$doc['id']) ?>" style="display:inline-flex;align-items:center;gap:6px;background:var(--accent-color,#1ABC9C);color:#fff;padding:8px 14px;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;">
                                    <i class="fa-solid fa-download"></i> Download
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
