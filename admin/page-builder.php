<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use Mori\AuditLog;
use function Mori\e;
use function Mori\asset;
use function Mori\flash;
use function Mori\slugify;
use function Mori\redirect;

Auth::requireLogin();
$db = Database::instance();

$id   = (int)($_GET['id'] ?? 0);
$page = $id ? $db->fetchOne('SELECT * FROM pages WHERE id = :id', ['id' => $id]) : null;
$isNew = !$page;

// AJAX Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!Csrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null)) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'CSRF']); exit;
    }
    $html = $_POST['html'] ?? '';
    $css  = $_POST['css'] ?? '';
    $body = ($css ? '<style>' . $css . '</style>' : '') . $html;

    $data = [
        'slug'   => slugify($_POST['slug'] ?? '') ?: slugify($_POST['title'] ?? ''),
        'locale' => in_array($_POST['locale'] ?? 'en', ['en','de'], true) ? $_POST['locale'] : 'en',
        'title'  => trim($_POST['title'] ?? ''),
        'body'   => $body,
        'status' => in_array($_POST['status'] ?? 'draft', ['draft','published'], true) ? $_POST['status'] : 'draft',
        'updated_by' => Auth::userId(),
    ];
    if ($data['title'] === '' || $data['slug'] === '') {
        echo json_encode(['ok' => false, 'error' => 'Title and slug required']); exit;
    }
    try {
        if ($isNew) {
            $newId = $db->insert('pages', $data);
            AuditLog::log(Auth::userId(), 'page_built', 'pages', $newId, $data['slug']);
            echo json_encode(['ok' => true, 'id' => $newId, 'redirect' => '/admin/page-builder.php?id=' . $newId]);
        } else {
            $db->update('pages', $data, ['id' => $id]);
            AuditLog::log(Auth::userId(), 'page_built', 'pages', $id, $data['slug']);
            echo json_encode(['ok' => true, 'id' => $id]);
        }
    } catch (\Throwable $ex) {
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
    }
    exit;
}

// Parse existing body
$existingHtml = '';
$existingCss  = '';
if ($page && !empty($page['body'])) {
    if (preg_match('/^<style>(.*?)<\/style>(.*)$/s', $page['body'], $m)) {
        $existingCss  = $m[1];
        $existingHtml = $m[2];
    } else {
        $existingHtml = $page['body'];
    }
}

$csrfToken = Csrf::token();
$pageTitle = $isNew ? 'New page' : $page['title'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($pageTitle) ?> — Visual Builder</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.21.13/dist/css/grapes.min.css">
<script src="https://cdn.jsdelivr.net/npm/grapesjs@0.21.13/dist/grapes.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-preset-webpage@1.0.3/dist/index.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,sans-serif;background:#ffffff}
.pb-bar{background:#122842;color:#fff;padding:10px 18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-bottom:1px solid rgba(255,255,255,.08);font-size:13px}
.pb-bar input,.pb-bar select{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:#fff;padding:7px 10px;border-radius:4px;font:inherit;font-size:13px}
.pb-bar input:focus,.pb-bar select:focus{outline:none;border-color:#1ABC9C}
.pb-btn{background:#1ABC9C;color:#fff;border:none;padding:8px 16px;border-radius:4px;font-weight:600;cursor:pointer;font:inherit;font-size:13px;display:inline-flex;align-items:center;gap:6px}
.pb-btn:hover{background:#16A085}
.pb-btn.ghost{background:transparent;color:rgba(255,255,255,.8);border:1px solid rgba(255,255,255,.2)}
.pb-btn.ghost:hover{background:rgba(255,255,255,.06)}
.pb-toast{position:fixed;top:60px;right:20px;background:#1ABC9C;color:#fff;padding:10px 20px;border-radius:6px;font-size:13px;font-weight:600;z-index:10000;transform:translateY(-30px);opacity:0;transition:all .25s ease;pointer-events:none}
.pb-toast.show{transform:translateY(0);opacity:1}
.pb-toast.err{background:#E74C3C}
#gjs{height:calc(100vh - 52px);overflow:hidden}
/* Force GrapesJS canvas white */
.gjs-cv-canvas{background:#fff !important}
.gjs-frame-wrapper{background:#fff !important}
.gjs-frame{background:#fff !important}
.gjs-cv-canvas__frames{background:#fff !important}
</style>
</head>
<body>

<div class="pb-bar">
    <a href="/admin/pages.php" class="pb-btn ghost"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <input type="text" id="pb_title" placeholder="Page title" value="<?= e($page['title'] ?? '') ?>" style="flex:1;min-width:180px">
    <input type="text" id="pb_slug" placeholder="slug" value="<?= e($page['slug'] ?? '') ?>" style="width:140px">
    <select id="pb_locale">
        <option value="en" <?= ($page['locale'] ?? 'en')==='en'?'selected':'' ?>>EN</option>
        <option value="de" <?= ($page['locale'] ?? '')==='de'?'selected':'' ?>>DE</option>
    </select>
    <select id="pb_status">
        <option value="draft" <?= ($page['status'] ?? 'draft')==='draft'?'selected':'' ?>>Draft</option>
        <option value="published" <?= ($page['status'] ?? '')==='published'?'selected':'' ?>>Published</option>
    </select>
    <?php if (!$isNew): ?>
    <a href="/admin/page-edit.php?id=<?= $id ?>" class="pb-btn ghost"><i class="fa-solid fa-pen"></i> Classic</a>
    <?php endif; ?>
    <button class="pb-btn" id="pb_save"><i class="fa-solid fa-save"></i> Save</button>
</div>

<div id="gjs"></div>
<div class="pb-toast" id="pb_toast"></div>

<script>
var csrfToken = <?= json_encode($csrfToken) ?>;
var pageId = <?= json_encode($id ?: null) ?>;
var initHtml = <?= json_encode($existingHtml ?: '<section style="padding:40px 30px;max-width:900px;margin:0 auto;"><h2>Start editing</h2><p>Click on any text to edit it, or drag blocks from the right panel.</p></section>') ?>;
var initCss = <?= json_encode($existingCss ?: '') ?>;

var editor = grapesjs.init({
    container: '#gjs',
    fromElement: false,
    height: '100%',
    width: 'auto',
    storageManager: false,
    plugins: ['grapesjs-preset-webpage'],
    pluginsOpts: {
        'grapesjs-preset-webpage': { useCustomTheme: false }
    },
    canvas: {
        styles: [
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
            '/css/bootstrap.min.css'
        ]
    }
});

// Inject styles into canvas iframe
function injectCanvasStyles() {
    try {
        var frame = editor.Canvas.getFrameEl();
        if (frame && frame.contentDocument && frame.contentDocument.head) {
            var doc = frame.contentDocument;
            // Remove any existing injected style
            var existing = doc.getElementById('mori-canvas-style');
            if (existing) existing.remove();
            var style = doc.createElement('style');
            style.id = 'mori-canvas-style';
            style.textContent = 'html,body{background:#fff!important;color:#2C3E50!important;font-family:Inter,Arial,sans-serif!important;font-size:15px!important;line-height:1.65!important;padding:0!important;margin:0!important;min-height:100%!important;visibility:visible!important;opacity:1!important;overflow:visible!important}[data-gjs-type="wrapper"],body>div:first-child,.gjs-dashed{position:relative!important;width:100%!important;min-height:100vh!important;padding:24px!important;background:#fff!important;color:#2C3E50!important;display:block!important;visibility:visible!important;opacity:1!important}h1,h2,h3,h4{color:#1B3A5C!important;font-weight:700!important}p{color:#5A6B7B!important;margin:0 0 1em!important}a{color:#1ABC9C!important}ul,ol{color:#5A6B7B!important;padding-left:24px!important}blockquote{border-left:3px solid #1ABC9C;padding-left:16px;color:#1B3A5C;font-style:italic}img{max-width:100%!important;height:auto!important}';
            doc.head.appendChild(style);
            console.log('Canvas styles injected');
            return true;
        }
    } catch(e) { console.warn('Canvas style injection failed:', e); }
    return false;
}

// IMPORTANT: Set content with INLINE styles so it's visible regardless of CSS
var safeHtml = initHtml;
// Wrap in a visible container if content doesn't have inline styles
if (safeHtml && safeHtml.indexOf('style=') === -1) {
    safeHtml = '<div style="background:#ffffff;color:#2C3E50;padding:30px;font-family:Inter,Arial,sans-serif;font-size:15px;line-height:1.65;min-height:200px;">' + safeHtml + '</div>';
}
editor.setComponents(safeHtml);
if (initCss) editor.setStyle(initCss);

// DEBUG: Also try adding a guaranteed-visible test block
editor.addComponents('<div style="background:#E8F8F4;border:2px solid #1ABC9C;color:#0F6B5C;padding:20px;margin:20px 0;border-radius:8px;font-size:16px;font-weight:600;">✓ GrapesJS is working — you can edit this text. Click on any element to select it.</div>');

// Try injecting styles immediately and on various events
injectCanvasStyles();
editor.on('load', function() {
    injectCanvasStyles();
    console.log('Editor loaded. Components:', editor.getComponents().length, 'HTML:', editor.getHtml().substring(0, 100));
});
editor.on('canvas:frame:load', function() {
    injectCanvasStyles();
    // Re-set components in case the frame reloaded
    if (editor.getComponents().length === 0) {
        editor.setComponents(initHtml);
    }
    console.log('Canvas frame loaded');
});
// Fallback: try again after a short delay
setTimeout(injectCanvasStyles, 500);
setTimeout(injectCanvasStyles, 1500);
setTimeout(function() {
    injectCanvasStyles();
    console.log('Final check — Components:', editor.getComponents().length);
}, 3000);

// Save
document.getElementById('pb_save').addEventListener('click', function() {
    var title = document.getElementById('pb_title').value.trim();
    if (!title) { toast('Title required', true); return; }
    var slug = document.getElementById('pb_slug').value.trim();
    if (!slug) slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    var fd = new FormData();
    fd.append('_csrf', csrfToken);
    fd.append('title', title);
    fd.append('slug', slug);
    fd.append('locale', document.getElementById('pb_locale').value);
    fd.append('status', document.getElementById('pb_status').value);
    fd.append('html', editor.getHtml());
    fd.append('css', editor.getCss());

    var url = pageId ? '/admin/page-builder.php?id=' + pageId : '/admin/page-builder.php';
    fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
        if (j.ok) { toast('Saved ✓'); if (j.redirect) setTimeout(function(){ location.href = j.redirect; }, 500); }
        else toast(j.error || 'Save failed', true);
    })
    .catch(function() { toast('Network error', true); });
});

// Ctrl+S
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); document.getElementById('pb_save').click(); }
});

function toast(msg, err) {
    var el = document.getElementById('pb_toast');
    el.textContent = msg;
    el.className = 'pb-toast show' + (err ? ' err' : '');
    setTimeout(function() { el.className = 'pb-toast'; }, 2000);
}
</script>
</body>
</html>
