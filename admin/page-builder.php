<?php
/**
 * Visual page builder powered by GrapesJS (free, MIT-licensed).
 * Wix-style drag-and-drop. Saves HTML + CSS into pages.body so the
 * public site renders it as-is.
 *
 * Usage:
 *   /admin/page-builder.php           — new draft
 *   /admin/page-builder.php?id=42     — edit existing page
 */
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

$id     = (int)($_GET['id'] ?? 0);
$page   = $id ? $db->fetchOne('SELECT * FROM pages WHERE id = :id', ['id' => $id]) : null;
$isNew  = !$page;

// Save (AJAX from GrapesJS or full POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!Csrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null)) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'CSRF']); exit;
    }
    $html = $_POST['html'] ?? '';
    $css  = $_POST['css']  ?? '';
    $body = '<style>' . $css . '</style>' . $html;

    $data = [
        'slug'             => slugify($_POST['slug'] ?? '') ?: slugify($_POST['title'] ?? ''),
        'locale'           => in_array($_POST['locale'] ?? 'en', ['en','de'], true) ? $_POST['locale'] : 'en',
        'title'            => trim($_POST['title'] ?? ''),
        'meta_title'       => trim($_POST['meta_title'] ?? '') ?: null,
        'meta_description' => trim($_POST['meta_description'] ?? '') ?: null,
        'body'             => $body,
        'status'           => in_array($_POST['status'] ?? 'draft', ['draft','published'], true) ? $_POST['status'] : 'draft',
        'updated_by'       => Auth::userId(),
    ];
    if ($data['title'] === '' || $data['slug'] === '') {
        echo json_encode(['ok' => false, 'error' => 'Title and slug are required.']); exit;
    }
    try {
        if ($isNew) {
            $newId = $db->insert('pages', $data);
            AuditLog::log(Auth::userId(), 'page_built', 'pages', $newId, $data['slug'] . ' (builder)');
            echo json_encode(['ok' => true, 'id' => $newId, 'redirect' => asset('admin/page-builder.php?id=' . $newId)]);
        } else {
            $db->update('pages', $data, ['id' => $id]);
            AuditLog::log(Auth::userId(), 'page_built', 'pages', $id, $data['slug'] . ' (builder)');
            echo json_encode(['ok' => true, 'id' => $id]);
        }
    } catch (\Throwable $ex) {
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
    }
    exit;
}

// Split existing body into html + css so GrapesJS can load it back
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

$pageTitle  = $isNew ? 'New page (visual builder)' : 'Editing ' . $page['title'];
$csrfToken  = Csrf::token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?> — Mori CMS</title>
<link rel="icon" type="image/png" href="<?= asset('assets/images/android-icon-192x192.png') ?>">

<!-- GrapesJS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.21.13/dist/css/grapes.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapick@0.1.10/dist/grapick.min.css">
<script src="https://cdn.jsdelivr.net/npm/grapesjs@0.21.13/dist/grapes.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-preset-webpage@1.0.3/dist/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-basic@1.0.2/dist/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-plugin-forms@2.0.6/dist/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-component-countdown@1.0.2/dist/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-tabs@1.0.6/dist/grapesjs-tabs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-custom-code@1.0.2/dist/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-tooltip@0.1.8/dist/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-typed@2.0.0/dist/grapesjs-typed.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-style-bg@2.0.3/dist/grapesjs-style-bg.min.js"></script>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" referrerpolicy="no-referrer">

<style>
    body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: #122842; }
    .pb-top {
        background: #122842; color: #fff;
        padding: 12px 22px; display: flex; align-items: center; gap: 18px; flex-wrap: wrap;
        border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .pb-top .brand { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600; }
    .pb-top .brand img { width: 28px; height: 28px; border-radius: 50%; }
    .pb-top .meta-fields { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 320px; }
    .pb-top input, .pb-top select {
        background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); color: #fff;
        padding: 8px 12px; border-radius: 5px; font-family: inherit; font-size: 13px;
    }
    .pb-top input::placeholder { color: rgba(255,255,255,.45); }
    .pb-top input:focus, .pb-top select:focus { outline: none; border-color: #1ABC9C; }
    .pb-top .title-input { flex: 1; min-width: 220px; }
    .pb-top .slug-input  { width: 180px; }
    .pb-top .lang-input  { width: 100px; }
    .pb-top .status-input{ width: 130px; }
    .pb-top .actions { display: flex; gap: 8px; }
    .pb-btn {
        background: #1ABC9C; color: #fff; border: 1px solid #1ABC9C;
        padding: 9px 16px; border-radius: 5px; font-weight: 600; font-size: 13px;
        cursor: pointer; font-family: inherit;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
    }
    .pb-btn:hover { background: #16A085; border-color: #16A085; }
    .pb-btn.ghost { background: transparent; color: rgba(255,255,255,.85); border-color: rgba(255,255,255,.20); }
    .pb-btn.ghost:hover { background: rgba(255,255,255,.08); color: #fff; }
    #gjs { height: calc(100vh - 64px); border: none; }

    /* GrapesJS palette match */
    .gjs-pn-panels { background: #122842; }
    .gjs-pn-views, .gjs-pn-views-container { background: #1B3A5C; }
    .gjs-block { background: #F5F7FA; color: #1B3A5C; font-size: 12px; border-color: #E1E7EE; }
    .gjs-block:hover { border-color: #1ABC9C; }

    .pb-toast {
        position: fixed; top: 80px; right: 24px;
        background: #1ABC9C; color: #fff; padding: 12px 22px; border-radius: 6px;
        font-size: 13px; font-weight: 600; box-shadow: 0 12px 30px rgba(0,0,0,.25);
        transform: translateY(-20px); opacity: 0;
        transition: all .25s ease; pointer-events: none; z-index: 10000;
    }
    .pb-toast.show { transform: translateY(0); opacity: 1; }
    .pb-toast.error { background: #E74C3C; }
</style>
</head>
<body>

<header class="pb-top">
    <a class="brand" href="<?= asset('admin/pages.php') ?>" style="color:#fff;text-decoration:none;">
        <img src="<?= asset('assets/images/android-icon-192x192.png') ?>" alt="Mori"> Mori CMS · Visual Builder
    </a>
    <div class="meta-fields">
        <input class="title-input" type="text" id="pb_title" placeholder="Page title *" value="<?= e($page['title'] ?? '') ?>">
        <input class="slug-input"  type="text" id="pb_slug"  placeholder="slug (auto)"  value="<?= e($page['slug'] ?? '') ?>">
        <select class="lang-input"   id="pb_locale">
            <option value="en" <?= ($page['locale'] ?? 'en')==='en'?'selected':'' ?>>English</option>
            <option value="de" <?= ($page['locale'] ?? '')==='de'?'selected':'' ?>>Deutsch</option>
        </select>
        <select class="status-input" id="pb_status">
            <option value="draft"     <?= ($page['status'] ?? 'draft')==='draft'?'selected':'' ?>>Draft</option>
            <option value="published" <?= ($page['status'] ?? '')==='published'?'selected':'' ?>>Published</option>
        </select>
    </div>
    <div class="actions">
        <a class="pb-btn ghost" href="<?= asset('admin/pages.php') ?>"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <?php if (!$isNew): ?>
        <a class="pb-btn ghost" href="<?= asset($page['slug'] . '.php') ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-eye"></i> Preview</a>
        <a class="pb-btn ghost" href="<?= asset('admin/page-edit.php?id=' . (int)$page['id']) ?>" title="Switch to classic editor"><i class="fa-solid fa-pen-to-square"></i> Classic editor</a>
        <?php endif; ?>
        <button class="pb-btn" id="pb_save"><i class="fa-solid fa-save"></i> Save page</button>
    </div>
</header>

<div id="gjs"><?= $existingHtml ?></div>

<div class="pb-toast" id="pb_toast"></div>

<script>
const csrfToken = <?= json_encode($csrfToken) ?>;
const pageId    = <?= json_encode($id ?: null) ?>;

const editor = grapesjs.init({
    container: '#gjs',
    height:    '100%',
    width:     'auto',
    storageManager: false,
    fromElement: true,
    canvas: {
        styles: [
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'
        ]
    },
    plugins: [
        'grapesjs-preset-webpage',
        'grapesjs-blocks-basic',
        'grapesjs-plugin-forms',
        'grapesjs-component-countdown',
        'grapesjs-tabs',
        'grapesjs-custom-code',
        'grapesjs-tooltip',
        'grapesjs-typed',
        'grapesjs-style-bg'
    ],
    pluginsOpts: {
        'grapesjs-preset-webpage': {
            modalImportTitle: 'Paste HTML to import',
            modalImportLabel: 'Replace the canvas with the pasted HTML+CSS',
            modalImportContent: '',
            useCustomTheme: false
        }
    },
    deviceManager: {
        devices: [
            { name: 'Desktop', width: '' },
            { name: 'Tablet',  width: '768px',  widthMedia: '991px' },
            { name: 'Mobile',  width: '375px',  widthMedia: '768px' }
        ]
    },
    assetManager: {
        upload:        '<?= asset('admin/api/upload-image.php') ?>',
        uploadName:    'file',
        headers:       { 'X-CSRF-Token': csrfToken },
        autoAdd:       true
    }
});

<?php if ($existingCss !== ''): ?>
editor.setStyle(<?= json_encode($existingCss) ?>);
<?php endif; ?>

// Auto-fill slug from title
const $title  = document.getElementById('pb_title');
const $slug   = document.getElementById('pb_slug');
const $locale = document.getElementById('pb_locale');
const $status = document.getElementById('pb_status');

function slugify(s) {
    return (s || '').toLowerCase()
        .replace(/[ş]/g,'s').replace(/[ç]/g,'c').replace(/[ğ]/g,'g')
        .replace(/[ı]/g,'i').replace(/[ö]/g,'o').replace(/[ü]/g,'u')
        .replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'');
}
$title.addEventListener('blur', () => {
    if (!$slug.value && $title.value) $slug.value = slugify($title.value);
});

function toast(msg, isError) {
    const el = document.getElementById('pb_toast');
    el.textContent = msg;
    el.classList.toggle('error', !!isError);
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2400);
}

document.getElementById('pb_save').addEventListener('click', async () => {
    if (!$title.value.trim()) { toast('Title is required', true); $title.focus(); return; }
    if (!$slug.value.trim()) { $slug.value = slugify($title.value); }

    const fd = new FormData();
    fd.append('_csrf',            csrfToken);
    fd.append('title',            $title.value);
    fd.append('slug',             $slug.value);
    fd.append('locale',           $locale.value);
    fd.append('status',           $status.value);
    fd.append('html',             editor.getHtml());
    fd.append('css',              editor.getCss());

    try {
        const url = pageId
            ? '<?= asset('admin/page-builder.php?id=') ?>' + pageId
            : '<?= asset('admin/page-builder.php') ?>';
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: fd
        });
        const json = await r.json();
        if (!json.ok) { toast(json.error || 'Save failed', true); return; }
        toast('Saved ✓');
        if (json.redirect) setTimeout(() => location.href = json.redirect, 600);
    } catch (e) {
        toast('Network error', true);
    }
});

// Keyboard shortcut: Ctrl/Cmd + S
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('pb_save').click();
    }
});
</script>

</body>
</html>
