<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use Mori\AuditLog;
use Mori\NavImport;
use function Mori\e;
use function Mori\asset;
use function Mori\flash;
use function Mori\format_date;
use function Mori\format_nav;
use function Mori\redirect;

Auth::requireLogin();
$db = Database::instance();
$allClasses = NavImport::classes($db);

// ---------------------------------------------------------------------------
// One-file upload for ALL share classes: template → upload → review → publish
// ---------------------------------------------------------------------------

// Templates (Excel by default, CSV on request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array($_GET['action'] ?? '', ['bulk_template', 'csv_template'], true)) {
    $layout = ($_GET['layout'] ?? '') === 'wide' ? 'wide' : 'long';
    $format = (($_GET['fmt'] ?? '') === 'csv' || ($_GET['action'] ?? '') === 'csv_template') ? 'csv' : 'xlsx';
    NavImport::sendTemplate($allClasses, $layout, $format);
}

// Step 1 — upload: keep the file, then show the review screen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_upload') {
    Csrf::requireValid();
    try {
        $f = $_FILES['file'] ?? null;
        $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) throw new \RuntimeException('Choose a file to upload.');
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            throw new \RuntimeException('The file is larger than the server allows (' . ini_get('upload_max_filesize') . ').');
        }
        if ($err !== UPLOAD_ERR_OK) throw new \RuntimeException('The upload failed (code ' . (int) $err . '). Please try again.');
        $name = basename((string) $f['name']);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xlsm', 'csv', 'txt', 'tsv'], true)) {
            throw new \RuntimeException('Please upload an Excel (.xlsx) or CSV file' . ($ext === 'xls' ? ' — old .xls files must be re-saved as .xlsx' : '') . '.');
        }
        $token = NavImport::stash((string) $f['tmp_name'], $name);
        $_SESSION['nav_import'] = array_filter(
            (array) ($_SESSION['nav_import'] ?? []),
            fn($m) => is_array($m) && ($m['created'] ?? 0) > time() - 21600
        );
        $_SESSION['nav_import'][$token] = ['name' => $name, 'created' => time()];
        redirect(asset('admin/performance.php?bulk=' . $token));
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect(asset('admin/performance.php'));
    }
}

// Step 2 — publish (re-reads and re-validates the kept file; all-or-nothing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_confirm') {
    Csrf::requireValid();
    $token = (string) ($_POST['token'] ?? '');
    $meta  = $_SESSION['nav_import'][$token] ?? null;
    $path  = $meta ? NavImport::stashPath($token) : null;
    if (!$meta || !$path) {
        flash('error', 'That upload has expired — please upload the file again.');
        redirect(asset('admin/performance.php'));
    }
    $mode = in_array($_POST['mode'] ?? '', ['upsert', 'add', 'replace'], true) ? $_POST['mode'] : 'upsert';
    try {
        $read = NavImport::readFile($path, (string) $meta['name']);
        $an   = NavImport::analyze($read['rows'], $allClasses, date('Y-m-d'));
        if ($an['errors']) throw new \RuntimeException('The file contains errors — nothing was published.');

        $res = NavImport::apply($db, $an['entries'], $mode);
        NavImport::unstash($token);
        unset($_SESSION['nav_import'][$token]);

        $nClasses = count(array_unique(array_map(fn($x) => $x['sc'], $an['entries'])));
        $msg = sprintf(
            'Prices published for %d share class%s — %d new, %d updated, %d unchanged',
            $nClasses, $nClasses === 1 ? '' : 'es', $res['inserted'], $res['updated'], $res['unchanged']
        );
        if ($mode === 'replace') $msg .= " ({$res['deleted']} previously stored entries were removed first)";
        if ($mode === 'add' && $res['unchanged'] > 0) $msg .= " ({$res['unchanged']} existing dates kept as they were)";
        AuditLog::log(Auth::userId(), 'nav_bulk_imported', 'nav_entries', null, $meta['name'] . " | mode={$mode} | {$msg}");
        flash('ok', $msg . '.');
        redirect(asset('admin/performance.php'));
    } catch (\Throwable $e) {
        flash('error', 'Nothing was published: ' . $e->getMessage());
        redirect(asset('admin/performance.php?bulk=' . $token));
    }
}

// Cancel a pending upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_cancel') {
    Csrf::requireValid();
    $token = (string) ($_POST['token'] ?? '');
    NavImport::unstash($token);
    unset($_SESSION['nav_import'][$token]);
    redirect(asset('admin/performance.php'));
}

// ---------------------------------------------------------------------------
// Single-entry management (unchanged behaviour)
// ---------------------------------------------------------------------------

// Add NAV entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_nav') {
    Csrf::requireValid();
    $scId = (int)($_POST['share_class_id'] ?? 0);
    try {
        $db->insert('nav_entries', [
            'share_class_id'  => $scId,
            'entry_date'      => $_POST['entry_date'],
            'nav'             => (float)$_POST['nav'],
            'benchmark_value' => $_POST['benchmark_value'] !== '' ? (float)$_POST['benchmark_value'] : null,
            'notes'           => trim($_POST['notes']) ?: null,
        ]);
        AuditLog::log(Auth::userId(), 'nav_added', 'nav_entries', null, "SC #{$scId} @ {$_POST['entry_date']}");
        flash('ok', 'NAV entry added.');
    } catch (\PDOException $e) {
        if (str_contains($e->getMessage(), 'uniq_class_date')) flash('error', 'A NAV for this share class and date already exists.');
        else flash('error', 'Could not save NAV: ' . $e->getMessage());
    }
    redirect(asset('admin/performance.php?class=' . $scId . '#history'));
}

// Toggle benchmark display
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_benchmark') {
    Csrf::requireValid();
    $val = isset($_POST['show_benchmark']) ? '1' : '0';
    $db->query(
        'INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
        ['k' => 'show_benchmark', 'v' => $val]
    );
    AuditLog::log(Auth::userId(), 'benchmark_toggled', 'settings', null, "show_benchmark={$val}");
    flash('ok', $val === '1' ? 'Benchmark is now visible on the performance chart.' : 'Benchmark is now hidden from the performance chart.');
    redirect(asset('admin/performance.php?class=' . (int)($_POST['class'] ?? 0)));
}

// Delete entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'del_nav') {
    Csrf::requireValid();
    $id = (int)$_POST['id'];
    $row = $db->fetchOne('SELECT * FROM nav_entries WHERE id=:id', ['id'=>$id]);
    if ($row) {
        $db->delete('nav_entries', ['id' => $id]);
        AuditLog::log(Auth::userId(), 'nav_deleted', 'nav_entries', $id, "SC #{$row['share_class_id']} @ {$row['entry_date']}");
        flash('ok', 'NAV entry deleted.');
    }
    redirect(asset('admin/performance.php?class=' . ($row['share_class_id'] ?? '') . '#history'));
}

// ---------------------------------------------------------------------------
// Review screen data
// ---------------------------------------------------------------------------
$bulk = null;
if (!empty($_GET['bulk'])) {
    $token = (string) $_GET['bulk'];
    $meta  = $_SESSION['nav_import'][$token] ?? null;
    $path  = $meta ? NavImport::stashPath($token) : null;
    if (!$meta || !$path) {
        flash('error', 'That upload has expired — please upload the file again.');
        redirect(asset('admin/performance.php'));
    }
    $bulk = ['token' => $token, 'name' => (string) $meta['name']];
    try {
        $read = NavImport::readFile($path, (string) $meta['name']);
        $an   = NavImport::analyze($read['rows'], $allClasses, date('Y-m-d'));
        $labels = [];
        foreach ($allClasses as $c) $labels[(int) $c['id']] = $c['name'] . ' (' . $c['isin'] . ')';
        $bulk += [
            'source'  => $read['source'],
            'rowsRead'=> max(0, count($read['rows']) - 1),
            'an'      => $an,
            'diff'    => $an['errors'] ? [] : NavImport::diff($db, $an['entries']),
            'jumps'   => $an['errors'] ? [] : NavImport::jumpWarnings($an['entries'], NavImport::prevNavLookup($db), $labels),
        ];
    } catch (\Throwable $e) {
        $bulk['fatal'] = $e->getMessage();
    }
}

// ---------------------------------------------------------------------------
// Page data
// ---------------------------------------------------------------------------
$latest = \Mori\latest_navs();
$entryCounts = [];
foreach ($db->fetchAll('SELECT share_class_id, COUNT(*) AS n FROM nav_entries GROUP BY share_class_id') as $r) {
    $entryCounts[(int) $r['share_class_id']] = (int) $r['n'];
}

$funds = $db->fetchAll('SELECT * FROM funds ORDER BY display_order');
$selectedFundId = (int)($_GET['fund'] ?? 0);
if (!$selectedFundId && !empty($_GET['class'])) {
    $selectedFundId = (int) $db->fetchColumn('SELECT fund_id FROM share_classes WHERE id = :id', ['id' => (int) $_GET['class']]);
}
if (!$selectedFundId) $selectedFundId = (int)($funds[0]['id'] ?? 0);
$shareClasses = $selectedFundId ? $db->fetchAll('SELECT * FROM share_classes WHERE fund_id=:f ORDER BY display_order', ['f'=>$selectedFundId]) : [];
$selectedScId = (int)($_GET['class'] ?? ($shareClasses[0]['id'] ?? 0));
$navEntries = $selectedScId ? $db->fetchAll('SELECT * FROM nav_entries WHERE share_class_id=:s ORDER BY entry_date DESC', ['s'=>$selectedScId]) : [];

$adminPage = ['title' => 'Performance (NAV)', 'crumb' => 'Publish NAV prices for all share classes from one file, plus manual entry and history'];
include __DIR__ . '/partials/layout-start.php';

$tpl = fn(string $layout, string $fmt = 'xlsx') => asset('admin/performance.php?action=bulk_template&layout=' . $layout . '&fmt=' . $fmt);
$pct = function (?float $from, float $to): string {
    if ($from === null || $from <= 0) return '—';
    return sprintf('%+.2f%%', ($to / $from - 1) * 100);
};
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><i class="fa-solid fa-circle-check"></i> <?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert error"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($err) ?></div><?php endif; ?>

<?php if ($bulk): /* ======================= REVIEW SCREEN ======================= */ ?>
<?php
    $an     = $bulk['an'] ?? null;
    $errors = $an['errors'] ?? [];
    $canPublish = empty($bulk['fatal']) && $an && !$errors && !empty($an['entries']);
    $warnings = array_merge($an['warnings'] ?? [], $bulk['jumps'] ?? []);
    $deleteTotal = 0;
    foreach (($bulk['diff'] ?? []) as $d) $deleteTotal += $d['existing_total'];
?>
<div class="a-card" style="margin-bottom:22px;border:2px solid var(--a-teal);">
    <div class="a-card__head">
        <h2><i class="fa-solid fa-magnifying-glass-chart"></i> Review before publishing</h2>
        <span class="a-badge muted"><i class="fa-regular fa-file"></i> <?= e($bulk['name']) ?></span>
    </div>
    <div class="a-card__body">

        <?php if (!empty($bulk['fatal'])): ?>
            <div class="a-alert error"><strong>The file could not be read.</strong><br><?= e($bulk['fatal']) ?></div>
        <?php else: ?>
            <p style="font-size:13px;color:var(--a-text-soft);margin-bottom:16px;">
                Read as <strong><?= $an['format'] === 'wide' ? 'one column per share class (history grid)' : 'one row per share class' ?></strong>
                from <?= $bulk['source'] === 'xlsx' ? 'an Excel file' : 'a CSV file' ?> ·
                <?= (int) $bulk['rowsRead'] ?> data row<?= $bulk['rowsRead'] === 1 ? '' : 's' ?> ·
                <strong><?= count($an['entries']) ?></strong> price<?= count($an['entries']) === 1 ? '' : 's' ?> found
                <?php if ($an['skipped_blank']): ?> · <?= (int) $an['skipped_blank'] ?> row<?= $an['skipped_blank'] === 1 ? '' : 's' ?> with an empty NAV skipped<?php endif; ?>
            </p>

            <?php if ($errors): ?>
            <div class="a-alert error">
                <strong><i class="fa-solid fa-circle-xmark"></i> <?= count($errors) ?> problem<?= count($errors) === 1 ? '' : 's' ?> found — nothing can be published until the file is corrected.</strong>
                <ul style="margin:8px 0 0 18px;">
                    <?php foreach (array_slice($errors, 0, 40) as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
                    <?php if (count($errors) > 40): ?><li>… and <?= count($errors) - 40 ?> more.</li><?php endif; ?>
                </ul>
                <div style="margin-top:8px;">Fix the file and upload it again.</div>
            </div>
            <?php endif; ?>

            <?php if ($warnings): ?>
            <div class="a-alert warn">
                <strong><i class="fa-solid fa-triangle-exclamation"></i> Please double-check</strong>
                <ul style="margin:8px 0 0 18px;">
                    <?php foreach (array_slice($warnings, 0, 30) as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
                    <?php if (count($warnings) > 30): ?><li>… and <?= count($warnings) - 30 ?> more.</li><?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($an['notes'])): ?>
            <div class="a-alert info">
                <ul style="margin:0 0 0 18px;">
                    <?php foreach (array_slice($an['notes'], 0, 15) as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!$errors && !empty($an['entries'])): ?>
            <div style="overflow-x:auto;margin-bottom:18px;">
            <table class="a-table">
                <thead><tr>
                    <th>Share class</th><th>ISIN</th><th style="text-align:right;">Prices</th><th>Date(s) in file</th>
                    <th style="text-align:right;">Latest NAV in file</th><th style="text-align:right;">Previous NAV</th><th style="text-align:right;">Change</th>
                    <th>Effect</th>
                </tr></thead>
                <tbody>
                <?php foreach ($allClasses as $c):
                    $id = (int) $c['id'];
                    $d = $bulk['diff'][$id] ?? null;
                ?>
                    <?php if (!$d): ?>
                    <tr style="opacity:.55;">
                        <td><?= e($c['name']) ?></td>
                        <td style="font-family:monospace;font-size:12px;"><?= e($c['isin']) ?></td>
                        <td colspan="6"><em>Not in this file — stays as it is</em><?= isset($latest[$id]) ? ' (currently ' . e(format_nav($latest[$id]['nav'], 'en')) . ' ' . e($c['currency']) . ', ' . e(format_date($latest[$id]['date'])) . ')' : '' ?></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td><strong><?= e($c['name']) ?></strong></td>
                        <td style="font-family:monospace;font-size:12px;"><?= e($c['isin']) ?></td>
                        <td style="text-align:right;"><?= (int) $d['rows'] ?></td>
                        <td><?= e(format_date($d['first'])) ?><?= $d['first'] !== $d['last'] ? ' → ' . e(format_date($d['last'])) : '' ?></td>
                        <td style="text-align:right;font-family:monospace;"><strong><?= e(format_nav($d['last_nav'], 'en')) ?></strong> <small><?= e($c['currency']) ?></small></td>
                        <td style="text-align:right;font-family:monospace;color:var(--a-muted);">
                            <?= $d['prev_nav'] !== null ? e(format_nav($d['prev_nav'], 'en')) . '<br><small>' . e(format_date($d['prev_date'])) . '</small>' : '—' ?>
                        </td>
                        <?php $big = $d['rows'] === 1 && $d['prev_nav'] !== null && $d['prev_nav'] > 0 && abs($d['last_nav'] / $d['prev_nav'] - 1) > NavImport::JUMP_WARN; ?>
                        <td style="text-align:right;font-family:monospace;<?= $big ? 'color:var(--a-danger);font-weight:700;' : '' ?>"><?= $big ? '<i class="fa-solid fa-triangle-exclamation"></i> ' : '' ?><?= e($d['rows'] === 1 ? $pct($d['prev_nav'], $d['last_nav']) : '') ?></td>
                        <td style="white-space:nowrap;">
                            <?php if ($d['new']): ?><span class="a-badge success"><?= (int) $d['new'] ?> new</span><?php endif; ?>
                            <?php if ($d['changed']): ?><span class="a-badge warning"><?= (int) $d['changed'] ?> changed</span><?php endif; ?>
                            <?php if ($d['unchanged']): ?><span class="a-badge muted"><?= (int) $d['unchanged'] ?> unchanged</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($canPublish): ?>
        <form method="post" class="a-form" id="bulkPublish"
              onsubmit="var m=this.querySelector('input[name=mode]:checked'); return !m || m.value!=='replace' || confirm('Replace all: this deletes ALL <?= (int) $deleteTotal ?> stored NAV entries of the share classes in this file before importing. Continue?');">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="bulk_confirm">
            <input type="hidden" name="token" value="<?= e($bulk['token']) ?>">
            <label style="font-weight:700;">If a date already has a price</label>
            <div style="background:var(--a-border-soft);padding:12px 16px;border-radius:8px;margin:8px 0 16px;">
                <label style="display:flex;align-items:flex-start;gap:8px;padding:5px 0;cursor:pointer;font-size:13px;font-weight:400;">
                    <input type="radio" name="mode" value="upsert" checked>
                    <span><strong>Update it</strong> (recommended) — new dates are added, changed prices are corrected, nothing is deleted.</span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:8px;padding:5px 0;cursor:pointer;font-size:13px;font-weight:400;">
                    <input type="radio" name="mode" value="add">
                    <span><strong>Keep it</strong> — only new dates are added; prices already published are left untouched.</span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:8px;padding:5px 0;cursor:pointer;font-size:13px;font-weight:400;color:var(--a-danger);">
                    <input type="radio" name="mode" value="replace">
                    <span><strong>Replace all history</strong> — deletes every stored price (<?= (int) $deleteTotal ?> entries) of the share classes in this file first. Only for a full reload.</span>
                </label>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button class="a-btn lg" type="submit"><i class="fa-solid fa-cloud-arrow-up"></i> Publish prices</button>
                <button class="a-btn ghost lg" type="submit" form="bulkCancel">Cancel</button>
            </div>
        </form>
        <?php else: ?>
            <button class="a-btn lg" type="submit" form="bulkCancel"><i class="fa-solid fa-rotate-left"></i> Upload a different file</button>
        <?php endif; ?>
        <form method="post" id="bulkCancel" style="display:none;">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="bulk_cancel">
            <input type="hidden" name="token" value="<?= e($bulk['token']) ?>">
        </form>
    </div>
</div>

<?php else: /* ======================= UPLOAD CARD ======================= */ ?>
<div class="a-card" style="margin-bottom:22px;">
    <div class="a-card__head">
        <h2><i class="fa-solid fa-file-arrow-up"></i> Upload prices — all share classes in one file</h2>
    </div>
    <div class="a-card__body">
        <p style="font-size:13.5px;color:var(--a-text-soft);line-height:1.65;margin-bottom:14px;">
            Upload <strong>one Excel (.xlsx) or CSV file</strong> with the NAV per share for any or all of the <?= count($allClasses) ?> share classes —
            a single day or a whole history. Prices are matched to share classes by <strong>ISIN</strong>. You will see a summary to check
            <strong>before</strong> anything is published.
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
            <a class="a-btn ghost" href="<?= e($tpl('long')) ?>"><i class="fa-solid fa-file-excel"></i> Daily prices template (.xlsx)</a>
            <a class="a-btn ghost" href="<?= e($tpl('wide')) ?>"><i class="fa-solid fa-table"></i> Price history template (.xlsx)</a>
        </div>
        <div style="font-size:12px;color:var(--a-muted);margin-bottom:18px;">
            Same templates as CSV: <a href="<?= e($tpl('long', 'csv')) ?>">daily prices</a> · <a href="<?= e($tpl('wide', 'csv')) ?>">price history</a>
        </div>

        <form method="post" enctype="multipart/form-data" class="a-form" style="margin-bottom:0;">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="bulk_upload">
            <label>Price file (.xlsx or .csv)</label>
            <input type="file" name="file" accept=".xlsx,.xlsm,.csv,.txt" required>
            <button class="a-btn lg" type="submit" style="margin-top:14px;"><i class="fa-solid fa-magnifying-glass"></i> Check file</button>
        </form>

        <details style="margin-top:18px;font-size:12.5px;color:var(--a-text-soft);line-height:1.7;">
            <summary style="cursor:pointer;font-weight:600;color:var(--a-text);">File format details</summary>
            <ul style="margin:8px 0 0 18px;">
                <li><strong>Daily prices</strong> (one row per share class): columns <code>Date</code>, <code>ISIN</code>, <code>NAV</code>; optional <code>Share class</code>, <code>Currency</code> (checked against the share class) and <code>Benchmark</code>. Rows with an empty NAV are skipped.</li>
                <li><strong>Price history</strong> (one row per date): a <code>Date</code> column plus one column per share class whose title contains its ISIN.</li>
                <li>Dates: real Excel dates, <code>2026-10-03</code>, <code>03/10/2026</code> (day/month/year), <code>03.10.2026</code> or <code>3 Oct 2026</code>. Future dates are rejected.</li>
                <li>Numbers: <code>142.8634</code> or <code>142,8634</code>; thousands separators are fine. Stored with 4 decimals.</li>
                <li>Nothing is saved unless the whole file is valid. Unusual moves (over 15% versus the previous price) are highlighted for checking.</li>
            </ul>
        </details>
    </div>
</div>
<?php endif; ?>

<!-- Currently published -->
<div class="a-card" style="margin-bottom:22px;">
    <div class="a-card__head"><h2><i class="fa-solid fa-tags"></i> Currently published prices</h2></div>
    <div class="a-card__body" style="padding:0;overflow-x:auto;">
        <table class="a-table">
            <thead><tr><th>Fund</th><th>Share class</th><th>ISIN</th><th style="text-align:right;">Latest NAV</th><th>NAV date</th><th style="text-align:right;">Stored prices</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($allClasses as $c): $id = (int) $c['id']; $l = $latest[$id] ?? null; ?>
                <tr>
                    <td><small><?= e($c['fund_name']) ?></small></td>
                    <td><strong><?= e($c['name']) ?></strong></td>
                    <td style="font-family:monospace;font-size:12px;"><?= e($c['isin']) ?></td>
                    <td style="text-align:right;font-family:monospace;"><?= $l ? '<strong>' . e(format_nav($l['nav'], 'en')) . '</strong> <small>' . e($c['currency']) . '</small>' : '<span style="color:var(--a-muted);">—</span>' ?></td>
                    <td><?= $l ? e(format_date($l['date'])) : '<span style="color:var(--a-muted);">no prices yet</span>' ?></td>
                    <td style="text-align:right;"><?= (int) ($entryCounts[$id] ?? 0) ?></td>
                    <td style="text-align:right;"><a href="<?= asset('admin/performance.php?class=' . $id . '#history') ?>" class="a-btn ghost sm">History</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Selector -->
<div class="a-card" style="margin-bottom:22px;" id="history">
    <div class="a-card__head"><h2><i class="fa-solid fa-clock-rotate-left"></i> One share class — manual entry &amp; history</h2></div>
    <div class="a-card__body">
        <form method="get" class="a-form row" style="margin-bottom:0;">
            <div>
                <label>Fund</label>
                <select name="fund" onchange="this.form.submit()">
                    <?php foreach ($funds as $f): ?>
                    <option value="<?= e($f['id']) ?>" <?= $selectedFundId==$f['id']?'selected':'' ?>><?= e($f['name_en']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Share class</label>
                <select name="class" onchange="this.form.submit()">
                    <?php foreach ($shareClasses as $sc): ?>
                    <option value="<?= e($sc['id']) ?>" <?= $selectedScId==$sc['id']?'selected':'' ?>><?= e($sc['name']) ?> — <?= e($sc['currency']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedScId): ?>
<div class="a-grid" style="grid-template-columns:1fr 1fr;gap:22px;">
    <!-- Add NAV -->
    <div class="a-card">
        <div class="a-card__head"><h2>Add a single NAV</h2></div>
        <div class="a-card__body">
            <form method="post" class="a-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="add_nav">
                <input type="hidden" name="share_class_id" value="<?= e($selectedScId) ?>">
                <div class="row">
                    <div><label>Date *</label><input type="date" name="entry_date" required value="<?= e(date('Y-m-d')) ?>"></div>
                    <div><label>NAV *</label><input type="number" name="nav" step="0.0001" min="0.0001" required></div>
                    <div><label>Benchmark</label><input type="number" name="benchmark_value" step="0.0001"></div>
                </div>
                <label>Notes</label>
                <input type="text" name="notes" placeholder="Optional">
                <button class="a-btn" type="submit" style="margin-top:14px;"><i class="fa-solid fa-plus"></i> Add entry</button>
            </form>
        </div>
    </div>

    <!-- Benchmark toggle -->
    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2>Chart display</h2></div>
        <div class="a-card__body">
            <form method="post" class="a-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="toggle_benchmark">
                <input type="hidden" name="class" value="<?= e($selectedScId) ?>">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:500;">
                    <input type="checkbox" name="show_benchmark" value="1" <?= \Mori\setting('show_benchmark', '1') === '1' ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span>Show benchmark on the NAV chart  <span style="font-size:11px;color:var(--a-muted);font-weight:400;">— untick to hide benchmark line from all share class charts on the public site</span></span>
                </label>
            </form>
        </div>
    </div>
</div>

<!-- Entries -->
<div class="a-card" style="margin-top:22px;">
    <div class="a-card__head">
        <h2>NAV history — <?= count($navEntries) ?> entries</h2>
    </div>
    <div class="a-card__body" style="padding:0;">
        <?php if (empty($navEntries)): ?>
        <div style="padding:30px;text-align:center;color:var(--a-muted);">No NAV entries yet for this share class.</div>
        <?php else: ?>
        <table class="a-table">
            <thead><tr><th>Date</th><th>NAV</th><th>Benchmark</th><th>Notes</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($navEntries as $n): ?>
                <tr>
                    <td><strong><?= e(format_date($n['entry_date'])) ?></strong></td>
                    <td style="font-family:monospace;"><?= e(number_format((float)$n['nav'], 4)) ?></td>
                    <td style="font-family:monospace;color:var(--a-muted);"><?= $n['benchmark_value'] !== null ? e(number_format((float)$n['benchmark_value'], 4)) : '—' ?></td>
                    <td><small><?= e($n['notes'] ?? '') ?></small></td>
                    <td style="text-align:right;">
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this NAV entry?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="del_nav">
                            <input type="hidden" name="id" value="<?= e($n['id']) ?>">
                            <button class="a-btn danger sm" type="submit"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
