<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Database;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Bad request'); }

try {
    $db = Database::instance();
    $doc = $db->fetchOne('SELECT * FROM documents WHERE id = :id', ['id' => $id]);
    if (!$doc) { http_response_code(404); exit('Document not found'); }

    $path = dirname(__DIR__) . '/uploads/documents/' . $doc['file_path'];
    if (!is_readable($path)) { http_response_code(404); exit('File missing on disk'); }

    // ?view=1 → serve inline for the in-page PDF preview; only real
    // downloads bump the counter.
    $inline = ($_GET['view'] ?? '') === '1';

    if (!$inline) {
        try { $db->query('UPDATE documents SET download_count = download_count + 1 WHERE id = :id', ['id' => $id]); }
        catch (\Throwable) {}
    }

    header('Content-Type: ' . ($inline ? 'application/pdf' : ($doc['mime_type'] ?: 'application/octet-stream')));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . basename($doc['file_name']) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($path);
} catch (\Throwable $e) {
    http_response_code(500);
    exit('Server error');
}
