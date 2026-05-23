<?php
/**
 * Mori Capital — One-time installation wizard.
 * Run this from the browser ONCE, then delete the file.
 *
 * What it does:
 *   1. Validates the .env file is present and reachable
 *   2. Tests the database connection
 *   3. Runs db/schema.sql then db/seed.sql
 *   4. Creates the first super_admin user
 *
 * After completion: delete install.php from the server.
 */
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

// =============================================================================
// SAFETY: if there's already an active super_admin, this installer is sealed.
// Delete the file (or temporarily deactivate the super_admin) to re-run.
// =============================================================================
$installerSealed = false;
try {
    $cnt = \Mori\Database::instance()->fetchColumn(
        'SELECT COUNT(*) FROM users WHERE role = "super_admin" AND status = "active"'
    );
    $installerSealed = ((int)$cnt > 0);
} catch (\Throwable) {
    $installerSealed = false; // DB unreachable yet — installer must be open
}
if ($installerSealed) {
    http_response_code(403);
    echo '<!DOCTYPE html><meta charset=utf-8><title>Installer sealed</title>'
       . '<body style="font-family:Inter,system-ui,sans-serif;max-width:600px;margin:80px auto;padding:30px;'
       . 'background:#FDECEC;border:1px solid #E57373;border-radius:10px;color:#8B2C2C;">'
       . '<h1 style="margin:0 0 12px;">Installer is sealed</h1>'
       . '<p>A super-admin already exists in the database. For safety, this installer '
       . 'cannot be re-run while the system is in production.</p>'
       . '<p><strong>Delete <code>install.php</code> from the server.</strong></p>'
       . '<p style="font-size:13px;color:#6B2222;">If you genuinely need to re-install '
       . '(e.g. restoring a backup), do it from the database directly.</p>'
       . '</body>';
    exit;
}

$step  = $_GET['step'] ?? '1';
$error = null;
$ok    = null;

// =============================================================================
// STEP 4 — Create admin user (POST)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_admin') {
    if (!\Mori\Csrf::verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid form submission (CSRF). Please reload the page.';
        $step  = '4';
    } else
    try {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $name  = trim($_POST['name'] ?? '');
        $pass  = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \Exception('Invalid email.');
        if (strlen($pass) < 12) throw new \Exception('Password must be at least 12 characters.');
        if ($name === '') throw new \Exception('Name is required.');

        $db = \Mori\Database::instance();
        $exists = $db->fetchColumn('SELECT id FROM users WHERE email = :e', ['e' => $email]);

        if ($exists) {
            $db->update('users', [
                'name'          => $name,
                'password_hash' => \Mori\Auth::hash($pass),
                'role'          => 'super_admin',
                'status'        => 'active',
            ], ['id' => $exists]);
        } else {
            $db->insert('users', [
                'email'         => $email,
                'name'          => $name,
                'password_hash' => \Mori\Auth::hash($pass),
                'role'          => 'super_admin',
                'status'        => 'active',
            ]);
        }

        $ok = 'Admin user created. <strong>Now delete install.php from the server.</strong>';
        $step = 'done';
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

// =============================================================================
// STEP 3 — Import schema + seed (GET ?step=3)
// =============================================================================
if ($step === '3' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $db = \Mori\Database::instance()->pdo();

        // Run base schema + seed, then apply every migration. All are idempotent.
        $existingMigrations = glob(__DIR__ . '/db/migrations/*.sql') ?: [];
        $migrationFiles = array_map('basename', $existingMigrations);
        // Drop the internal _schema_migrations.sql — schema.sql creates that table itself.
        $migrationFiles = array_filter($migrationFiles, fn($f) => $f[0] !== '_');
        sort($migrationFiles);

        foreach (['db/schema.sql', 'db/seed.sql'] as $file) {
            $path = __DIR__ . '/' . $file;
            if (!is_readable($path)) throw new \Exception("Missing file: {$file}");
            $sql = file_get_contents($path);

            // Strip block comments
            $sql = preg_replace('!/\*.*?\*/!s', '', $sql);

            // Strip single-line -- comments
            $cleaned = [];
            foreach (preg_split("/\r?\n/", $sql) as $line) {
                $trim = ltrim($line);
                if (str_starts_with($trim, '--')) continue;
                $cleaned[] = $line;
            }
            $sql = implode("\n", $cleaned);

            // Split on `;` followed by newline OR end-of-string — naive but
            // safe for our scripts (no semicolons inside string literals).
            $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') continue;
                $db->exec($stmt);
            }
        }

        // Now apply migrations (records them in schema_migrations so the
        // admin Database panel knows they're done).
        $migrator = new \Mori\Migrator();
        $appliedCount = 0;
        foreach ($migrationFiles as $mf) {
            $r = $migrator->apply($mf, null, 'applied during install');
            if (!empty($r['applied'])) $appliedCount++;
        }

        $ok = 'Schema and seed imported successfully'
            . ($appliedCount ? " (+ $appliedCount migrations)" : '')
            . '.';
    } catch (\Throwable $e) {
        $error = 'Import failed: ' . $e->getMessage();
    }
}

// =============================================================================
// STEP 2 — Test DB connection (GET ?step=2)
// =============================================================================
if ($step === '2') {
    try {
        \Mori\Database::instance()->pdo();
        $ok = 'Connected to <code>' . htmlspecialchars((string) \Mori\Config::get('DB_NAME')) . '</code> as <code>' . htmlspecialchars((string) \Mori\Config::get('DB_USER')) . '</code>.';
    } catch (\Throwable $e) {
        $error = 'Connection failed: ' . $e->getMessage();
    }
}

// =============================================================================
// View
// =============================================================================
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Mori Capital — Install</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root { --navy:#1B3A5C; --teal:#1ABC9C; --soft:#F5F7FA; --border:#E1E7EE; --text:#5A6B7B; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:'Inter',system-ui,sans-serif; background:var(--soft); color:#2C3E50; padding:40px 20px; }
    .wrap { max-width:680px; margin:0 auto; background:#fff; border:1px solid var(--border); border-radius:14px; padding:38px 42px; box-shadow:0 18px 50px rgba(8,18,33,.08); }
    h1 { color:var(--navy); margin:0 0 4px; font-size:24px; }
    .sub { color:var(--text); font-size:14px; margin-bottom:24px; }
    .steps { display:flex; gap:8px; margin-bottom:24px; }
    .step { flex:1; padding:10px 14px; border:1px solid var(--border); border-radius:8px; font-size:12px; font-weight:600; color:var(--text); }
    .step.active { background:var(--navy); color:#fff; border-color:var(--navy); }
    .step.done { background:var(--teal); color:#fff; border-color:var(--teal); }
    .msg { padding:14px 16px; border-radius:8px; margin-bottom:18px; font-size:14px; line-height:1.55; }
    .msg.ok { background:#E8F8F4; border:1px solid var(--teal); color:#0F6B5C; }
    .msg.error { background:#FDECEC; border:1px solid #E57373; color:#8B2C2C; }
    label { display:block; font-size:13px; font-weight:600; color:var(--navy); margin:14px 0 6px; }
    input[type=text],input[type=email],input[type=password] { width:100%; padding:11px 13px; font-size:14px; border:1px solid var(--border); border-radius:6px; font-family:inherit; }
    .btn { display:inline-block; background:var(--teal); color:#fff; border:none; padding:12px 22px; font-size:14px; font-weight:600; border-radius:6px; cursor:pointer; text-decoration:none; }
    .btn:hover { background:#16A085; }
    .btn-ghost { background:transparent; color:var(--navy); border:1px solid var(--border); }
    .row { display:flex; gap:10px; margin-top:18px; flex-wrap:wrap; }
    code { background:var(--soft); border:1px solid var(--border); padding:1px 6px; border-radius:4px; font-size:12px; }
    pre { background:#0E1F36; color:#9EC9E2; padding:14px 16px; border-radius:6px; overflow:auto; font-size:12px; line-height:1.55; }
</style>
</head>
<body>
<div class="wrap">
    <h1>Mori Capital — Installer</h1>
    <div class="sub">One-time setup. Delete <code>install.php</code> after step 4.</div>

    <div class="steps">
        <div class="step <?= $step==='1'?'active':($step>'1'?'done':'') ?>">1. Config</div>
        <div class="step <?= $step==='2'?'active':($step>'2'?'done':'') ?>">2. DB connect</div>
        <div class="step <?= $step==='3'?'active':($step>'3'?'done':'') ?>">3. Schema</div>
        <div class="step <?= ($step==='4'||$step==='done')?'active':'' ?>">4. Admin</div>
    </div>

    <?php if ($error): ?>
        <div class="msg error"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
        <div class="msg ok"><?= $ok ?></div>
    <?php endif; ?>

    <?php if ($step === '1'): ?>
        <h2 style="font-size:18px;color:var(--navy);margin:0 0 8px;">Step 1 — Verify .env file</h2>
        <p>Copy <code>.env.example</code> to <code>.env</code> at the project root and fill in the database credentials and SMTP settings.</p>
        <pre>cp .env.example .env
nano .env</pre>
        <p>Once saved, proceed to test the database connection.</p>
        <div class="row">
            <a class="btn" href="?step=2">Next — test database</a>
        </div>

    <?php elseif ($step === '2'): ?>
        <h2 style="font-size:18px;color:var(--navy);margin:0 0 8px;">Step 2 — Database connection</h2>
        <p>Confirms PHP can connect to MySQL using the credentials in <code>.env</code>.</p>
        <div class="row">
            <a class="btn-ghost btn" href="?step=1">← Back</a>
            <?php if ($ok): ?>
                <a class="btn" href="?step=3">Next — import schema</a>
            <?php else: ?>
                <a class="btn" href="?step=2">Retry</a>
            <?php endif; ?>
        </div>

    <?php elseif ($step === '3'): ?>
        <h2 style="font-size:18px;color:var(--navy);margin:0 0 8px;">Step 3 — Import schema &amp; seed</h2>
        <p>Runs <code>db/schema.sql</code> then <code>db/seed.sql</code> against the configured database.</p>
        <div class="row">
            <a class="btn-ghost btn" href="?step=2">← Back</a>
            <?php if ($ok): ?>
                <a class="btn" href="?step=4">Next — create admin user</a>
            <?php else: ?>
                <a class="btn" href="?step=3">Retry import</a>
            <?php endif; ?>
        </div>

    <?php elseif ($step === '4'): ?>
        <h2 style="font-size:18px;color:var(--navy);margin:0 0 8px;">Step 4 — First admin user</h2>
        <p>Creates a <strong>super_admin</strong> account you'll use to sign into the CMS.</p>
        <form method="post">
            <?= \Mori\Csrf::field() ?>
            <input type="hidden" name="action" value="create_admin">
            <label>Email</label>
            <input type="email" name="email" required placeholder="admin@mori-capital.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <label>Full name</label>
            <input type="text" name="name" required placeholder="Hakan Sahin" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            <label>Password (min 12 chars, use a strong unique value)</label>
            <input type="password" name="password" required minlength="12">
            <div class="row">
                <a class="btn-ghost btn" href="?step=3">← Back</a>
                <button class="btn" type="submit">Create admin &amp; finish</button>
            </div>
        </form>

    <?php elseif ($step === 'done'): ?>
        <h2 style="font-size:18px;color:var(--navy);margin:0 0 8px;">All done</h2>
        <p>Installation complete. For security, <strong>delete <code>install.php</code> now</strong> and visit the admin panel.</p>
        <pre>rm install.php</pre>
        <div class="row">
            <a class="btn" href="/admin/login.php">Go to admin login →</a>
            <a class="btn-ghost btn" href="/">Visit homepage</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
