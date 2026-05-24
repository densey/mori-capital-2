<?php
/**
 * Generic AJAX file upload endpoint for admin forms.
 * Returns JSON { ok: true, path: 'uploads/media/2026/abc-filename.jpg' }
 * Used by team photo, insight cover, fund cover, OG image etc.
 */
require __DIR__ . '/../../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use function Mori\setting;

header('Content-Type: application/json');
Auth::requireLogin();

try {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null;
    if (!Csrf::verify($token)) {
        throw new \Exception('CSRF token missing or invalid');
    }
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new \Exception('No file uploaded');
    }

    $maxBytes = (int)(setting('upload_max_mb', '20')) * 1024 * 1024;
    if ($_FILES['file']['size'] > $maxBytes) throw new \Exception('File too large');

    $name = $_FILES['file']['name'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
        throw new \Exception('Only images allowed (jpg, png, gif, webp)');
    }

    $year   = date('Y');
    $dir    = dirname(__DIR__, 2) . '/uploads/media/' . $year;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $safe   = bin2hex(random_bytes(6)) . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    $target = $dir . '/' . $safe;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
        throw new \Exception('Could not store file');
    }

    $info = @getimagesize($target);
    $path = 'uploads/media/' . $year . '/' . $safe;

    Database::instance()->insert('media', [
        'file_path'   => $year . '/' . $safe,
        'file_name'   => $name,
        'file_size'   => filesize($target),
        'mime_type'   => mime_content_type($target) ?: null,
        'width'       => $info[0] ?? null,
        'height'      => $info[1] ?? null,
        'folder'      => $_POST['folder'] ?? 'uploads',
        'uploaded_by' => Auth::userId(),
    ]);

    echo json_encode(['ok' => true, 'path' => $path, 'url' => '/' . $path]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
