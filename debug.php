<?php
/**
 * Quick diagnostic — run from browser to check migration + document status.
 * DELETE THIS FILE after debugging.
 */
require __DIR__ . '/src/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== MORI CAPITAL — DIAGNOSTIC ===\n\n";

// 1. DB connection
try {
    $db = \Mori\Database::instance();
    echo "✓ DB connected: " . \Mori\Config::get('DB_NAME') . "\n\n";
} catch (\Throwable $e) {
    echo "✗ DB FAILED: " . $e->getMessage() . "\n";
    exit;
}

// 2. Tables
echo "--- TABLES ---\n";
$tables = $db->fetchAll("SHOW TABLES");
foreach ($tables as $t) {
    $name = array_values($t)[0];
    $count = $db->fetchColumn("SELECT COUNT(*) FROM `$name`");
    echo "  $name: $count rows\n";
}

// 3. Migrations
echo "\n--- MIGRATIONS ---\n";
try {
    $applied = $db->fetchAll("SELECT file, applied_at FROM schema_migrations ORDER BY id");
    if (empty($applied)) {
        echo "  (no migrations applied yet)\n";
    }
    foreach ($applied as $m) {
        echo "  ✓ {$m['file']} — {$m['applied_at']}\n";
    }
} catch (\Throwable $e) {
    echo "  ✗ schema_migrations table missing: " . $e->getMessage() . "\n";
}

// 4. Pending migrations
echo "\n--- PENDING MIGRATIONS ---\n";
try {
    $migrator = new \Mori\Migrator();
    $pending = $migrator->pending();
    if (empty($pending)) {
        echo "  (none — all applied)\n";
    }
    foreach ($pending as $f) {
        echo "  ⏳ $f\n";
    }
} catch (\Throwable $e) {
    echo "  ✗ Migrator error: " . $e->getMessage() . "\n";
}

// 5. Documents
echo "\n--- DOCUMENTS ---\n";
$docCount = $db->fetchColumn("SELECT COUNT(*) FROM documents");
echo "  Total: $docCount\n";
if ($docCount > 0) {
    $byType = $db->fetchAll("SELECT document_type, COUNT(*) as cnt FROM documents GROUP BY document_type ORDER BY cnt DESC");
    foreach ($byType as $r) echo "    {$r['document_type']}: {$r['cnt']}\n";
}

// 6. Funds + share classes
echo "\n--- FUNDS ---\n";
$funds = $db->fetchAll("SELECT id, slug, name_en FROM funds ORDER BY display_order");
foreach ($funds as $f) {
    $scCount = $db->fetchColumn("SELECT COUNT(*) FROM share_classes WHERE fund_id = :id", ['id' => $f['id']]);
    echo "  {$f['name_en']} (id={$f['id']}): $scCount share classes\n";
}

// 7. Team
echo "\n--- TEAM ---\n";
$team = $db->fetchAll("SELECT name, photo_path FROM team_members ORDER BY display_order");
foreach ($team as $t) {
    $exists = file_exists(__DIR__ . '/' . $t['photo_path']) ? '✓' : '✗';
    echo "  $exists {$t['name']} — {$t['photo_path']}\n";
}

// 8. File system check
echo "\n--- FILE SYSTEM ---\n";
$dirs = [
    'uploads/documents/all',
    'uploads/documents/fund-updates',
    'uploads/documents/accounts',
    'uploads/documents/policies',
    'assets/images/team',
];
foreach ($dirs as $d) {
    $path = __DIR__ . '/' . $d;
    if (is_dir($path)) {
        $count = count(glob("$path/*.pdf")) + count(glob("$path/*.jpg")) + count(glob("$path/*.png"));
        echo "  ✓ $d: $count files\n";
    } else {
        echo "  ✗ $d: MISSING\n";
    }
}

// 9. Settings
echo "\n--- KEY SETTINGS ---\n";
$keys = ['site_title', 'contact_email', 'mfsa_license', 'google_analytics_id', 'logo_light_path', 'logo_dark_path'];
foreach ($keys as $k) {
    $v = \Mori\setting($k, '(not set)');
    echo "  $k: $v\n";
}

echo "\n=== END DIAGNOSTIC ===\n";
