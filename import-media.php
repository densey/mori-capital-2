<?php
/**
 * One-shot import: seeds the Media section (media_items) from
 * db/seed/media-items.json — mirroring the old live site's media.html.
 *
 *   - press_release items: the PDF is downloaded from mori-capital.com into
 *     uploads/media/press/ and the row points at the LOCAL copy (is_external
 *     = 0) so links survive the production cut-over.
 *   - article / video / podcast items: kept as external links (is_external
 *     = 1), opened in a new tab.
 *
 * Run ONCE via CLI or as super_admin in the browser. Idempotent: matches
 * existing rows by (title_en + item_date) and updates them instead of
 * inserting duplicates, and skips PDF downloads that already exist.
 *
 *   php import-media.php            # live
 *   php import-media.php --dry      # dry run, no writes / downloads
 *   /import-media.php?dry=1         # browser dry run
 */
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use Mori\Auth;
use Mori\Database;

$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    Auth::requireRole('super_admin');
    header('Content-Type: text/plain; charset=utf-8');
}

$db = Database::instance();

$UPLOAD_REL = 'uploads/media/press';                 // stored path prefix (relative to web root)
$UPLOAD_DIR = __DIR__ . '/' . $UPLOAD_REL;
if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);

$DRY_RUN = isset($_GET['dry']) || in_array('--dry', $argv ?? [], true);

$seedFile = __DIR__ . '/db/seed/media-items.json';
if (!is_readable($seedFile)) {
    fwrite(STDERR, "Seed file not found: db/seed/media-items.json\n");
    exit(1);
}
$items = json_decode((string) file_get_contents($seedFile), true) ?: [];

// Ensure the table exists even if the migration hasn't been run yet.
$db->query("CREATE TABLE IF NOT EXISTS media_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_date DATE NULL,
    source VARCHAR(200) NULL,
    title_en VARCHAR(400) NOT NULL,
    title_de VARCHAR(400) NULL,
    url_en VARCHAR(700) NULL,
    url_de VARCHAR(700) NULL,
    item_type ENUM('press_release','article','video','podcast') NOT NULL DEFAULT 'article',
    is_external TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('published','draft') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_date (status, item_date),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$summary = ['inserted' => 0, 'updated' => 0, 'failed' => 0, 'downloaded' => 0, 'bytes' => 0];

function download(string $url, string $target): ?int {
    $ctx = stream_context_create(['http' => ['timeout' => 40, 'follow_location' => 1, 'user_agent' => 'MoriCMS-import/1.0']]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 200) return null;
    file_put_contents($target, $data);
    return strlen($data);
}

/**
 * For a press-release PDF URL, download it locally and return the stored
 * relative path. On failure, returns the original URL (so the link still
 * works against the old site) and flags it.
 */
function localise_pdf(?string $url, string $UPLOAD_REL, string $UPLOAD_DIR, bool $DRY_RUN, array &$summary): ?string {
    $url = trim((string) $url);
    if ($url === '') return null;
    $basename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename(parse_url($url, PHP_URL_PATH) ?: $url));
    if ($basename === '' || !str_contains($basename, '.')) $basename .= '.pdf';
    $stored = $UPLOAD_REL . '/' . $basename;
    $target = $UPLOAD_DIR . '/' . $basename;

    if (is_file($target) && filesize($target) > 200) {
        return $stored; // already downloaded
    }
    if ($DRY_RUN) {
        echo "      [DRY-DOWNLOAD] $url\n";
        return $stored;
    }
    $bytes = download($url, $target);
    if ($bytes === null) {
        echo "      [WARN] download failed, keeping external link: $url\n";
        return $url; // fall back to the external URL
    }
    $summary['downloaded']++;
    $summary['bytes'] += $bytes;
    echo "      [PDF] $stored ($bytes bytes)\n";
    return $stored;
}

echo "════════════════════════════════════════════════════════════════\n";
echo "  Mori Capital — Media import" . ($DRY_RUN ? "  (DRY RUN)" : "") . "\n";
echo "  Items in seed : " . count($items) . "\n";
echo "════════════════════════════════════════════════════════════════\n\n";

// Newest first → smallest display_order.
usort($items, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

$order = 0;
foreach ($items as $it) {
    $order += 10;
    $type   = $it['type'] ?? 'article';
    $isPress = $type === 'press_release';
    $titleEn = trim((string) ($it['title_en'] ?? ''));
    $date    = ($it['date'] ?? '') ?: null;

    echo "• [{$type}] {$titleEn}\n";

    $urlEn = $it['url_en'] ?? '';
    $urlDe = $it['url_de'] ?? '';

    if ($isPress) {
        $urlEn = localise_pdf($urlEn, $UPLOAD_REL, $UPLOAD_DIR, $DRY_RUN, $summary) ?? '';
        if (trim((string) ($it['url_de'] ?? '')) !== '') {
            $urlDe = localise_pdf($urlDe, $UPLOAD_REL, $UPLOAD_DIR, $DRY_RUN, $summary) ?? '';
        } else {
            $urlDe = '';
        }
    }
    // is_external: press releases that resolved to a local path are NOT
    // external; everything else (and press PDFs that failed to download) is.
    $isExternal = (!$isPress || str_starts_with((string) $urlEn, 'http')) ? 1 : 0;

    $row = [
        'item_date'     => $date,
        'source'        => trim((string) ($it['source'] ?? '')),
        'title_en'      => $titleEn,
        'title_de'      => trim((string) ($it['title_de'] ?? '')),
        'url_en'        => $urlEn,
        'url_de'        => $urlDe,
        'item_type'     => $type,
        'is_external'   => $isExternal,
        'display_order' => $order,
        'status'        => 'published',
    ];

    if ($DRY_RUN) {
        echo "      [DRY] ext={$isExternal} order={$order}\n\n";
        $summary['inserted']++;
        continue;
    }

    // Idempotent: match by title_en + item_date.
    $existing = $db->fetchColumn(
        'SELECT id FROM media_items WHERE title_en = :t AND (item_date <=> :d) LIMIT 1',
        ['t' => $titleEn, 'd' => $date]
    );
    if ($existing) {
        $db->update('media_items', $row, ['id' => (int) $existing]);
        echo "      [UPDATE #{$existing}] ext={$isExternal}\n\n";
        $summary['updated']++;
    } else {
        $id = $db->insert('media_items', $row);
        echo "      [OK #{$id}] ext={$isExternal}\n\n";
        $summary['inserted']++;
    }
}

echo "────────────────────────────────────────────────────────────────\n";
echo "  Inserted : {$summary['inserted']}\n";
echo "  Updated  : {$summary['updated']}\n";
echo "  PDFs DLd : {$summary['downloaded']} (" . round($summary['bytes'] / 1024) . " KB)\n";
echo "  Failed   : {$summary['failed']}\n";
echo "  Done." . ($DRY_RUN ? " (dry run — nothing written)" : "") . "\n";
