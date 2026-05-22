<?php
/**
 * TinyMCE inline image upload endpoint.
 * Returns JSON: { location: '/uploads/media/<path>' }
 */
require __DIR__ . '/../../src/bootstrap.php';

use Mori\Auth;
use Mori\Database;
use function Mori\setting;

header('Content-Type: application/json');
Auth::requireLogin();

try {
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new \Exception('No file');
    }
    $maxBytes = (int)(setting('upload_max_mb', '20')) * 1024 * 1024;
    if ($_FILES['file']['size'] > $maxBytes) throw new \Exception('Too large');
    $name = $_FILES['file']['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg'], true)) throw new \Exception('Bad type');

    $year = date('Y');
    $dir  = dirname(__DIR__, 2) . '/uploads/media/' . $year;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $safe   = bin2hex(random_bytes(6)) . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    $target = $dir . '/' . $safe;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) throw new \Exception('Move failed');

    $info = @getimagesize($target);
    Database::instance()->insert('media', [
        'file_path' => $year . '/' . $safe,
        'file_name' => $name,
        'file_size' => filesize($target),
        'mime_type' => mime_content_type($target) ?: null,
        'width'     => $info[0] ?? null,
        'height'    => $info[1] ?? null,
        'folder'    => 'inline',
        'uploaded_by' => Auth::userId(),
    ]);
    echo json_encode(['location' => '/uploads/media/' . $year . '/' . $safe]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
