<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use Mori\AuditLog;
use Mori\Migrator;
use function Mori\e;
use function Mori\asset;
use function Mori\flash;
use function Mori\format_date;
use function Mori\redirect;

Auth::requireRole('super_admin');

$migrator = new Migrator();

// --- Re-run base schema (idempotent: CREATE TABLE IF NOT EXISTS) -------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sync_schema') {
    Csrf::requireValid();
    try {
        $sql = file_get_contents(dirname(__DIR__) . '/db/schema.sql');
        $sql = preg_replace('!/\*.*?\*/!s', '', (string)$sql);
        $lines = [];
        foreach (preg_split("/\r?\n/", (string)$sql) as $line) {
            if (str_starts_with(ltrim($line), '--')) continue;
            $lines[] = $line;
        }
        $statements = preg_split('/;\s*(?:\r?\n|$)/', implode("\n", $lines));
        $pdo = Database::instance()->pdo();
        $n = 0;
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;
            $pdo->exec($stmt);
            $n++;
        }
        AuditLog::log(Auth::userId(), 'schema_synced', 'schema_migrations', null, "$n statements");
        flash('ok', "Schema re-synced ($n statements). No data was deleted (CREATE TABLE IF NOT EXISTS is safe).");
    } catch (\Throwable $e) {
        flash('error', 'Schema sync failed: ' . $e->getMessage());
    }
    redirect(asset('admin/database.php'));
}

// --- Apply a single pending migration ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    Csrf::requireValid();
    $file = (string)($_POST['file'] ?? '');
    try {
        $r = $migrator->apply($file, Auth::userId());
        if (!empty($r['applied'])) {
            AuditLog::log(Auth::userId(), 'migration_applied', 'schema_migrations', null, $file);
            flash('ok', "Migration applied: <code>" . htmlspecialchars($file) . "</code> ({$r['statements']} statements).");
        } else {
            flash('warn', "Migration skipped: " . ($r['reason'] ?? 'unknown reason'));
        }
    } catch (\Throwable $e) {
        flash('error', "Migration failed (<code>$file</code>): " . $e->getMessage());
    }
    redirect(asset('admin/database.php'));
}

// --- Apply ALL pending in order ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_all') {
    Csrf::requireValid();
    try {
        $results = $migrator->applyAll(Auth::userId());
        $applied = array_keys(array_filter($results, fn($r) => !empty($r['applied'])));
        AuditLog::log(Auth::userId(), 'migrations_batch', 'schema_migrations', null, count($applied) . ' files');
        flash('ok', count($applied) ? ('Applied: ' . implode(', ', $applied)) : 'Nothing to apply.');
    } catch (\Throwable $e) {
        flash('error', 'Batch failed: ' . $e->getMessage());
    }
    redirect(asset('admin/database.php'));
}

$pending = $migrator->pending();
$applied = $migrator->applied();
$drift   = $migrator->drift();

// DB stats
try {
    $stats = [
        'pages'    => (int) Database::instance()->fetchColumn('SELECT COUNT(*) FROM pages'),
        'funds'    => (int) Database::instance()->fetchColumn('SELECT COUNT(*) FROM funds'),
        'sc'       => (int) Database::instance()->fetchColumn('SELECT COUNT(*) FROM share_classes'),
        'docs'     => (int) Database::instance()->fetchColumn('SELECT COUNT(*) FROM documents'),
        'nav'      => (int) Database::instance()->fetchColumn('SELECT COUNT(*) FROM nav_entries'),
        'team'     => (int) Database::instance()->fetchColumn('SELECT COUNT(*) FROM team_members'),
        'users'    => (int) Database::instance()->fetchColumn('SELECT COUNT(*) FROM users'),
    ];
} catch (\Throwable) { $stats = []; }

$adminPage = ['title' => 'Database', 'crumb' => 'Migrations · schema sync · maintenance (super_admin)'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok  = flash('ok')):    ?><div class="a-alert ok"><?= $ok ?></div><?php endif; ?>
<?php if ($wr  = flash('warn')):  ?><div class="a-alert warn"><?= $wr ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert error"><?= $err ?></div><?php endif; ?>

<!-- Quick stats -->
<div class="a-grid a-grid-4" style="margin-bottom:22px;">
    <div class="a-stat"><div class="a-stat__icon"><i class="fa-solid fa-file-lines"></i></div><div><div class="a-stat__lbl">Pages</div><div class="a-stat__val"><?= e($stats['pages'] ?? 0) ?></div></div></div>
    <div class="a-stat"><div class="a-stat__icon navy"><i class="fa-solid fa-chart-pie"></i></div><div><div class="a-stat__lbl">Funds / SC</div><div class="a-stat__val"><?= e($stats['funds'] ?? 0) ?> / <?= e($stats['sc'] ?? 0) ?></div></div></div>
    <div class="a-stat"><div class="a-stat__icon"><i class="fa-regular fa-folder-open"></i></div><div><div class="a-stat__lbl">Documents</div><div class="a-stat__val"><?= e($stats['docs'] ?? 0) ?></div></div></div>
    <div class="a-stat"><div class="a-stat__icon navy"><i class="fa-solid fa-chart-line"></i></div><div><div class="a-stat__lbl">NAV entries</div><div class="a-stat__val"><?= e($stats['nav'] ?? 0) ?></div></div></div>
</div>

<!-- Schema sync -->
<div class="a-card" style="margin-bottom:22px;">
    <div class="a-card__head"><h2><i class="fa-solid fa-rotate"></i> Schema sync</h2></div>
    <div class="a-card__body">
        <p style="font-size:13.5px;color:var(--a-text-soft);margin-bottom:14px;">Re-runs <code>db/schema.sql</code>. Every table uses <code>CREATE TABLE IF NOT EXISTS</code>, so this is safe and never deletes data — but it also won't ALTER existing tables. Use migrations below to apply structural changes.</p>
        <form method="post">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="sync_schema">
            <button class="a-btn ghost" type="submit" onclick="return confirm('Re-run schema.sql? (CREATE TABLE IF NOT EXISTS — no data is lost)')"><i class="fa-solid fa-rotate"></i> Re-run schema.sql</button>
        </form>
    </div>
</div>

<!-- Pending migrations -->
<div class="a-card" style="margin-bottom:22px;">
    <div class="a-card__head">
        <h2><i class="fa-solid fa-arrow-up-from-bracket"></i> Pending migrations <span class="a-badge <?= $pending?'warning':'success' ?>" style="margin-left:8px;"><?= count($pending) ?></span></h2>
        <?php if ($pending): ?>
        <form method="post" style="margin:0;">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="apply_all">
            <button class="a-btn" type="submit" onclick="return confirm('Apply ALL <?= count($pending) ?> pending migrations in order?')"><i class="fa-solid fa-rocket"></i> Apply all</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="a-card__body" style="padding:0;">
        <?php if (empty($pending)): ?>
        <div style="padding:24px;text-align:center;color:var(--a-success);font-size:14px;"><i class="fa-solid fa-circle-check"></i> Database is up to date.</div>
        <?php else: ?>
        <table class="a-table">
            <thead><tr><th>File</th><th>Size</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pending as $f):
                $size = filesize(dirname(__DIR__) . '/db/migrations/' . $f);
            ?>
                <tr>
                    <td><code style="font-family:monospace;font-size:12.5px;"><?= e($f) ?></code></td>
                    <td style="color:var(--a-muted);font-size:12px;"><?= e(\Mori\format_bytes((int)$size)) ?></td>
                    <td style="text-align:right;">
                        <details style="display:inline-block;margin-right:6px;">
                            <summary class="a-btn ghost sm" style="cursor:pointer;display:inline-flex;"><i class="fa-solid fa-eye"></i> Preview</summary>
                            <pre style="position:absolute;background:#0E1F36;color:#9EC9E2;border-radius:6px;padding:14px;font-size:11px;line-height:1.55;max-width:600px;max-height:280px;overflow:auto;text-align:left;margin-top:8px;box-shadow:0 12px 32px rgba(0,0,0,.25);z-index:5;"><?= htmlspecialchars($migrator->preview($f)) ?></pre>
                        </details>
                        <form method="post" style="display:inline;">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="apply">
                            <input type="hidden" name="file" value="<?= e($f) ?>">
                            <button class="a-btn sm" type="submit" onclick="return confirm('Apply <?= e($f) ?>?')"><i class="fa-solid fa-play"></i> Apply</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Applied -->
<div class="a-card" style="margin-bottom:22px;">
    <div class="a-card__head"><h2><i class="fa-solid fa-check"></i> Applied migrations <span class="a-badge muted" style="margin-left:8px;"><?= count($applied) ?></span></h2></div>
    <div class="a-card__body" style="padding:0;">
        <?php if (empty($applied)): ?>
        <div style="padding:24px;text-align:center;color:var(--a-muted);font-size:13.5px;">No migrations applied yet.</div>
        <?php else: ?>
        <table class="a-table">
            <thead><tr><th>File</th><th>When</th><th>Checksum</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($applied as $f => $row): ?>
                <tr>
                    <td><code style="font-family:monospace;font-size:12.5px;"><?= e($f) ?></code></td>
                    <td><small><?= e(format_date($row['applied_at'], 'd M Y H:i')) ?></small></td>
                    <td><small style="font-family:monospace;color:var(--a-muted);" title="<?= e($row['checksum']) ?>"><?= e(substr($row['checksum'], 0, 12)) ?>…</small></td>
                    <td><?php if (isset($drift[$f])): ?><span class="a-badge warning"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($drift[$f]) ?></span><?php else: ?><span class="a-badge success">OK</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
