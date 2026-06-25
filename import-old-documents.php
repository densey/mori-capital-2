<?php
/**
 * One-shot import: pulls Other Documents (2024+) and Updates During Suspension
 * PDFs from mori-capital.com into the Mori Capital CMS.
 *
 * Run via CLI or browser ONCE after deploying the new dropdown structure.
 * Idempotent: skips documents already imported (matched by file_name).
 *
 *   php import-old-documents.php
 *
 * Reads from scratch/other-docs.json and scratch/update-docs.json (committed).
 */
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use Mori\Auth;
use Mori\Database;

// CLI-only or super_admin in browser
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    Auth::requireRole('super_admin');
    header('Content-Type: text/plain; charset=utf-8');
}

$db = Database::instance();

$SOURCE_BASE = 'https://www.mori-capital.com/';
$UPLOAD_DIR  = __DIR__ . '/uploads/documents/imported-other';
if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);

$DRY_RUN = isset($_GET['dry']) || in_array('--dry', $argv ?? [], true);

$otherJson  = __DIR__ . '/scratch/other-docs.json';
$updateJson = __DIR__ . '/scratch/update-docs.json';
if (!is_readable($otherJson) || !is_readable($updateJson)) {
    fwrite(STDERR, "Source JSON files not found in scratch/. Aborting.\n");
    exit(1);
}

$other  = json_decode((string)file_get_contents($otherJson), true) ?: [];
$updates = json_decode((string)file_get_contents($updateJson), true) ?: [];

$summary = ['skipped' => 0, 'inserted' => 0, 'failed' => 0, 'downloaded_bytes' => 0];

// --- detect language from title ----------------------------------------------
function detect_locale(string $title): string {
    $t = strtolower($title);
    if (preg_match('/\b(deutsch|german)\b/i', $title)) return 'de';
    if (preg_match('/\b(english|en)\b/i', $title))     return 'en';
    return 'any';
}

// Strip language tag from displayed title (we already store it in locale column)
function clean_title(string $title): string {
    $t = preg_replace('/[\s\-–—()]*(English|Deutsch|German|EN|DE)[\s)\-]*$/i', '', $title) ?? $title;
    return trim($t);
}

function download(string $url, string $target): ?int {
    $ctx = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 100) return null;
    file_put_contents($target, $data);
    return strlen($data);
}

function import_one(array $doc, string $category, ?int $year, $db, string $SOURCE_BASE, string $UPLOAD_DIR, bool $DRY_RUN, array &$summary): void {
    $href = ltrim($doc['href'], '/');
    $url  = $SOURCE_BASE . $href;
    $basename = basename($href);
    // Make file name safe + collision-free
    $stored = 'imported-other/' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $basename);

    // Already imported?
    $exists = $db->fetchColumn(
        'SELECT id FROM documents WHERE file_path = :p OR file_name = :n LIMIT 1',
        ['p' => $stored, 'n' => $basename]
    );

    $title  = clean_title((string)$doc['title']);
    $locale = detect_locale((string)$doc['title']);

    if ($exists) {
        // Update its category/year/description if needed
        if (!$DRY_RUN) {
            $db->update('documents', [
                'category'     => $category,
                'display_year' => $year,
                'description'  => $doc['title'],
                'locale'       => $locale,
            ], ['id' => (int)$exists]);
        }
        echo "  [SKIP/UPDATE] $title  (cat=$category, year=" . ($year ?: '-') . ", locale=$locale)\n";
        $summary['skipped']++;
        return;
    }

    $target = $UPLOAD_DIR . '/' . basename($stored);
    if (!is_file($target)) {
        if ($DRY_RUN) {
            echo "  [DRY-DOWNLOAD] $url\n";
        } else {
            $bytes = download($url, $target);
            if ($bytes === null) {
                echo "  [FAIL] could not download $url\n";
                $summary['failed']++;
                return;
            }
            $summary['downloaded_bytes'] += $bytes;
        }
    }

    if ($DRY_RUN) {
        echo "  [DRY-INSERT] $title  (cat=$category, year=" . ($year ?: '-') . ", locale=$locale)\n";
        $summary['inserted']++;
        return;
    }

    $size = is_file($target) ? filesize($target) : 0;
    $mime = is_file($target) ? (mime_content_type($target) ?: 'application/pdf') : 'application/pdf';

    $id = $db->insert('documents', [
        'fund_id'       => null,
        'document_type' => 'other',
        'category'      => $category,
        'title'         => $title,
        'description'   => $doc['title'],
        'file_path'     => $stored,
        'file_name'     => $basename,
        'file_size'     => $size,
        'mime_type'     => $mime,
        'document_date' => null,
        'display_year'  => $year,
        'locale'        => $locale,
        'uploaded_by'   => null,
    ]);
    echo "  [OK #$id] $title  ($size bytes, $locale)\n";
    $summary['inserted']++;
}

echo "════════════════════════════════════════════════════════════════\n";
echo "  Mori Capital — Other Documents + Suspension Updates import\n";
echo "  Source : $SOURCE_BASE\n";
echo "  Target : $UPLOAD_DIR\n";
echo "  Mode   : " . ($DRY_RUN ? 'DRY RUN' : 'LIVE') . "\n";
echo "════════════════════════════════════════════════════════════════\n";

echo "\n── Other Documents (2024+) ──\n";
foreach ($other as $doc) {
    $section = $doc['section'] ?? '';
    // Customer requested 2024+ only — skip earlier years
    if (!preg_match('/^(2024|2025|2026)$/', $section)) continue;
    $year = (int)$section;
    import_one($doc, 'other', $year, $db, $SOURCE_BASE, $UPLOAD_DIR, $DRY_RUN, $summary);
}

echo "\n── Updates During Suspension (all) ──\n";
foreach ($updates as $doc) {
    import_one($doc, 'suspension_update', null, $db, $SOURCE_BASE, $UPLOAD_DIR, $DRY_RUN, $summary);
}

echo "\n════════════════════════════════════════════════════════════════\n";
echo "  inserted:        " . $summary['inserted'] . "\n";
echo "  skipped/updated: " . $summary['skipped'] . "\n";
echo "  failed:          " . $summary['failed'] . "\n";
echo "  downloaded:      " . number_format($summary['downloaded_bytes'] / 1024 / 1024, 2) . " MB\n";
echo "════════════════════════════════════════════════════════════════\n";
