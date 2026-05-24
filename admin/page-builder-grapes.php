<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use Mori\AuditLog;
use function Mori\e;
use function Mori\asset;
use function Mori\slugify;

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
    $gjsData = $_POST['gjs_data'] ?? '';

    $body = '';
    if ($gjsData) {
        $body = '<!--GJS_DATA:' . base64_encode($gjsData) . "-->\n";
    }
    if ($css) {
        $body .= '<style>' . $css . '</style>';
    }
    $body .= $html;

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
            echo json_encode(['ok' => true, 'id' => $newId, 'redirect' => '/admin/page-builder-grapes.php?id=' . $newId]);
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
$existingGjsData = '';
if ($page && !empty($page['body'])) {
    $raw = $page['body'];
    // Strip GJS data comment
    if (preg_match('/^<!--GJS_DATA:(.*?)-->\n?(.*)$/s', $raw, $m)) {
        $existingGjsData = base64_decode($m[1]) ?: '';
        $raw = $m[2];
    }
    // Strip MORI_BLOCKS comment (from block editor)
    if (preg_match('/^<!--MORI_BLOCKS:.*?-->\n?(.*)$/s', $raw, $m)) {
        $raw = $m[1];
    }
    // Extract style tag
    if (preg_match('/^<style>(.*?)<\/style>(.*)$/s', $raw, $m)) {
        $existingCss  = $m[1];
        $existingHtml = $m[2];
    } else {
        $existingHtml = $raw;
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
<title><?= e($pageTitle) ?> — GrapesJS Editor</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/grapesjs@0.21.13/dist/css/grapes.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', system-ui, sans-serif; overflow: hidden; height: 100vh; background: #353535; }

/* Top bar */
.gjs-topbar {
    background: #122842; color: #fff; height: 48px;
    display: flex; align-items: center; gap: 10px; padding: 0 14px;
    z-index: 100; position: relative; flex-shrink: 0;
}
.gjs-topbar input, .gjs-topbar select {
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15);
    color: #fff; padding: 5px 10px; border-radius: 4px; font: inherit; font-size: 13px;
}
.gjs-topbar input:focus, .gjs-topbar select:focus { outline: none; border-color: #1ABC9C; }
.gjs-topbar input::placeholder { color: rgba(255,255,255,.35); }
.tb { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 4px;
    font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all .15s; }
.tb-p { background: #1ABC9C; color: #fff; }
.tb-p:hover { background: #16A085; }
.tb-g { background: transparent; color: rgba(255,255,255,.7); border: 1px solid rgba(255,255,255,.2); }
.tb-g:hover { background: rgba(255,255,255,.06); color: #fff; }
.gjs-topbar .sp { flex: 1; }

/* GrapesJS overrides for Mori theme */
.gjs-one-bg { background-color: #1B3A5C; }
.gjs-two-color { color: #ddd; }
.gjs-three-bg { background-color: #122842; }
.gjs-four-color, .gjs-four-color-h:hover { color: #1ABC9C; }

/* Editor fills remaining space */
#gjs-editor {
    height: calc(100vh - 48px);
    width: 100%;
    overflow: hidden;
}

/* Make sure canvas is white */
.gjs-cv-canvas { background: #fff !important; }

/* Toast */
.gjs-toast {
    position: fixed; top: 56px; right: 16px; padding: 10px 18px; border-radius: 6px;
    font-size: 13px; font-weight: 600; z-index: 999; transform: translateY(-20px);
    opacity: 0; transition: all .2s ease; pointer-events: none;
    background: #1ABC9C; color: #fff; font-family: Inter, sans-serif;
}
.gjs-toast.show { transform: translateY(0); opacity: 1; }
.gjs-toast.err { background: #E74C3C; }

/* Debug overlay */
.gjs-debug {
    position: fixed; bottom: 10px; left: 10px; background: rgba(0,0,0,.85);
    color: #0f0; font-family: monospace; font-size: 11px; padding: 8px 12px;
    border-radius: 6px; z-index: 999; max-width: 400px; line-height: 1.5;
    display: none;
}
.gjs-debug.visible { display: block; }
</style>
</head>
<body>

<!-- Top bar -->
<div class="gjs-topbar">
    <a href="/admin/pages.php" class="tb tb-g"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <input type="text" id="g_title" placeholder="Page title" value="<?= e($page['title'] ?? '') ?>" style="flex:1;min-width:120px;max-width:280px">
    <input type="text" id="g_slug" placeholder="slug" value="<?= e($page['slug'] ?? '') ?>" style="width:100px">
    <select id="g_locale">
        <option value="en" <?= ($page['locale'] ?? 'en')==='en'?'selected':'' ?>>EN</option>
        <option value="de" <?= ($page['locale'] ?? '')==='de'?'selected':'' ?>>DE</option>
    </select>
    <select id="g_status">
        <option value="draft" <?= ($page['status'] ?? 'draft')==='draft'?'selected':'' ?>>Draft</option>
        <option value="published" <?= ($page['status'] ?? '')==='published'?'selected':'' ?>>Published</option>
    </select>
    <span class="sp"></span>
    <?php if (!$isNew): ?>
    <a href="/admin/page-builder.php?id=<?= $id ?>" class="tb tb-g"><i class="fa-solid fa-cubes"></i> Block Editor</a>
    <a href="/admin/page-edit.php?id=<?= $id ?>" class="tb tb-g"><i class="fa-solid fa-pen"></i> Classic</a>
    <?php endif; ?>
    <button class="tb tb-g" id="g_debug_toggle"><i class="fa-solid fa-bug"></i></button>
    <button class="tb tb-p" id="g_save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
</div>

<!-- GrapesJS editor container -->
<div id="gjs-editor"></div>

<!-- Toast -->
<div class="gjs-toast" id="gToast"></div>

<!-- Debug panel -->
<div class="gjs-debug" id="gDebug"></div>

<script src="https://unpkg.com/grapesjs@0.21.13/dist/grapes.min.js"></script>
<script>
(function() {
'use strict';

var CSRF = <?= json_encode($csrfToken) ?>;
var PAGE_ID = <?= json_encode($id ?: null) ?>;
var INIT_HTML = <?= json_encode($existingHtml ?: '') ?>;
var INIT_CSS = <?= json_encode($existingCss ?: '') ?>;
var INIT_GJS = <?= json_encode($existingGjsData ?: '') ?>;

// ── Utility ──
function toast(msg, err) {
    var el = document.getElementById('gToast');
    el.textContent = msg;
    el.className = 'gjs-toast show' + (err ? ' err' : '');
    setTimeout(function() { el.className = 'gjs-toast'; }, 2500);
}

function debug(msg) {
    var el = document.getElementById('gDebug');
    el.innerHTML += msg + '<br>';
    el.scrollTop = el.scrollHeight;
    console.log('[GJS]', msg);
}

// ── Default content if empty ──
var defaultHtml = '<section style="padding:40px 30px;max-width:900px;margin:0 auto;font-family:Inter,Arial,sans-serif;">' +
    '<h2 style="color:#1B3A5C;font-size:28px;margin-bottom:16px;">Page Title</h2>' +
    '<p style="color:#5A6B7B;font-size:15px;line-height:1.7;">Start editing this page. Click on any text to edit it, or use the blocks panel on the right to add new elements.</p>' +
    '</section>';

var contentHtml = INIT_HTML && INIT_HTML.trim() ? INIT_HTML : defaultHtml;

// ── Protected CSS: ensures content is always visible ──
var protectedCss = [
    '* { box-sizing: border-box; }',
    'body { margin: 0; padding: 0; background: #fff !important; min-height: 100vh; }',
    'html { background: #fff !important; }',
    '[data-gjs-type="wrapper"] { min-height: 100vh !important; padding: 0 !important; overflow: visible !important; background: #fff; }',
    'h1, h2, h3, h4 { color: #1B3A5C; margin: 0 0 .5em; font-weight: 700; }',
    'p { color: #5A6B7B; line-height: 1.7; margin: 0 0 1em; }',
    'a { color: #1ABC9C; }',
    'img { max-width: 100%; height: auto; }',
    'section { position: relative; }',
    'ul, ol { color: #5A6B7B; padding-left: 24px; }',
    'blockquote { border-left: 3px solid #1ABC9C; padding: 12px 20px; margin: 0 0 1em; color: #1B3A5C; font-style: italic; }',
    '.row { display: flex; flex-wrap: wrap; margin: 0 -15px; }',
    '.row > div { padding: 0 15px; }',
    '.btn-mori { display: inline-block; padding: 12px 28px; background: #1ABC9C; color: #fff; border-radius: 4px; font-weight: 600; text-decoration: none; font-size: 14px; cursor: pointer; }',
].join('\n');

debug('Init: HTML length=' + contentHtml.length + ', CSS length=' + INIT_CSS.length);

// ── Initialize GrapesJS ──
var editor = grapesjs.init({
    container: '#gjs-editor',
    fromElement: false,
    height: '100%',
    width: 'auto',
    storageManager: false,
    protectedCss: protectedCss,
    canvas: {
        styles: [
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'
        ]
    },
    panels: { defaults: [] },
    deviceManager: {
        devices: [
            { name: 'Desktop', width: '' },
            { name: 'Tablet', width: '768px', widthMedia: '992px' },
            { name: 'Mobile', width: '375px', widthMedia: '480px' }
        ]
    },
    blockManager: {
        appendTo: '#gjs-editor',
        blocks: []
    },
    styleManager: {
        appendTo: '#gjs-editor'
    },
    layerManager: {
        appendTo: '#gjs-editor'
    }
});

debug('Editor created');

// ── Add custom blocks ──
var bm = editor.BlockManager;

bm.add('section', {
    label: '<i class="fa fa-object-group"></i> Section',
    category: 'Layout',
    content: '<section style="padding:40px 30px;"><h2 style="color:#1B3A5C;">Section Title</h2><p style="color:#5A6B7B;">Section content goes here.</p></section>',
});

bm.add('text-block', {
    label: '<i class="fa fa-paragraph"></i> Text',
    category: 'Basic',
    content: '<p style="color:#5A6B7B;font-size:15px;line-height:1.7;">Insert your text here.</p>',
});

bm.add('heading', {
    label: '<i class="fa fa-heading"></i> Heading',
    category: 'Basic',
    content: '<h2 style="color:#1B3A5C;font-size:28px;">Heading</h2>',
});

bm.add('image', {
    label: '<i class="fa fa-image"></i> Image',
    category: 'Basic',
    content: { type: 'image' },
    activate: true,
});

bm.add('button', {
    label: '<i class="fa fa-square"></i> Button',
    category: 'Basic',
    content: '<a class="btn-mori" href="#" style="display:inline-block;padding:12px 28px;background:#1ABC9C;color:#fff;border-radius:4px;font-weight:600;text-decoration:none;font-size:14px;">Button</a>',
});

bm.add('2-columns', {
    label: '<i class="fa fa-columns"></i> 2 Columns',
    category: 'Layout',
    content: '<div style="display:flex;gap:20px;padding:20px 0;">' +
        '<div style="flex:1;padding:10px;"><p style="color:#5A6B7B;">Column 1</p></div>' +
        '<div style="flex:1;padding:10px;"><p style="color:#5A6B7B;">Column 2</p></div>' +
        '</div>',
});

bm.add('3-columns', {
    label: '<i class="fa fa-th"></i> 3 Columns',
    category: 'Layout',
    content: '<div style="display:flex;gap:20px;padding:20px 0;">' +
        '<div style="flex:1;padding:10px;"><p style="color:#5A6B7B;">Column 1</p></div>' +
        '<div style="flex:1;padding:10px;"><p style="color:#5A6B7B;">Column 2</p></div>' +
        '<div style="flex:1;padding:10px;"><p style="color:#5A6B7B;">Column 3</p></div>' +
        '</div>',
});

bm.add('divider', {
    label: '<i class="fa fa-minus"></i> Divider',
    category: 'Basic',
    content: '<hr style="border:0;border-top:1px solid #E1E7EE;margin:20px 0;">',
});

bm.add('spacer', {
    label: '<i class="fa fa-arrows-up-down"></i> Spacer',
    category: 'Basic',
    content: '<div style="height:40px;"></div>',
});

bm.add('quote', {
    label: '<i class="fa fa-quote-left"></i> Quote',
    category: 'Basic',
    content: '<blockquote style="border-left:3px solid #1ABC9C;padding:12px 20px;margin:20px 0;"><p style="color:#1B3A5C;font-style:italic;">A meaningful quote goes here.</p></blockquote>',
});

bm.add('video', {
    label: '<i class="fa fa-video"></i> Video',
    category: 'Media',
    content: { type: 'video', src: 'https://www.youtube.com/embed/dQw4w9WgXcQ', style: { width: '100%', height: '350px' } },
});

bm.add('list', {
    label: '<i class="fa fa-list"></i> List',
    category: 'Basic',
    content: '<ul style="color:#5A6B7B;padding-left:24px;line-height:1.8;"><li>Item one</li><li>Item two</li><li>Item three</li></ul>',
});

bm.add('colored-section', {
    label: '<i class="fa fa-fill-drip"></i> Colored Section',
    category: 'Layout',
    content: '<section style="padding:50px 30px;background:#F7F9FC;text-align:center;"><h2 style="color:#1B3A5C;font-size:28px;margin-bottom:12px;">Call to Action</h2><p style="color:#5A6B7B;font-size:16px;max-width:600px;margin:0 auto 20px;">Describe your value proposition here.</p><a style="display:inline-block;padding:12px 28px;background:#1ABC9C;color:#fff;border-radius:4px;font-weight:600;text-decoration:none;" href="#">Get Started</a></section>',
});

// ── Build custom panels ──
var pn = editor.Panels;

// Device buttons
pn.addPanel({
    id: 'devices-c',
    el: '',
    buttons: [{
        id: 'device-desktop', command: 'set-device-desktop',
        className: 'fa fa-desktop', active: true, togglable: false,
    }, {
        id: 'device-tablet', command: 'set-device-tablet',
        className: 'fa fa-tablet',
    }, {
        id: 'device-mobile', command: 'set-device-mobile',
        className: 'fa fa-mobile',
    }]
});

editor.Commands.add('set-device-desktop', { run: function(e) { e.setDevice('Desktop'); } });
editor.Commands.add('set-device-tablet', { run: function(e) { e.setDevice('Tablet'); } });
editor.Commands.add('set-device-mobile', { run: function(e) { e.setDevice('Mobile'); } });

// View buttons
pn.addPanel({
    id: 'views-c',
    el: '',
    buttons: [{
        id: 'show-blocks', command: 'show-blocks', active: true,
        className: 'fa fa-th-large', togglable: false,
    }, {
        id: 'show-layers', command: 'show-layers',
        className: 'fa fa-bars',
    }, {
        id: 'show-style', command: 'show-styles',
        className: 'fa fa-paint-brush',
    }]
});

editor.Commands.add('show-blocks', {
    run: function(editor) {
        var bm = editor.Panels.getButton('views-c', 'show-blocks');
        if (bm) bm.set('active', true);
    }
});
editor.Commands.add('show-layers', {
    run: function(editor) {
        var lm = editor.Panels.getButton('views-c', 'show-layers');
        if (lm) lm.set('active', true);
    }
});
editor.Commands.add('show-styles', {
    run: function(editor) {
        var sm = editor.Panels.getButton('views-c', 'show-style');
        if (sm) sm.set('active', true);
    }
});

// ── CRITICAL: Load content AFTER frame is ready ──
var contentLoaded = false;

function loadContent() {
    if (contentLoaded) return;
    contentLoaded = true;
    debug('Loading content into editor...');

    // If we have stored GrapesJS project data, use it (preserves component structure)
    if (INIT_GJS) {
        try {
            var gjsProject = JSON.parse(INIT_GJS);
            editor.loadProjectData(gjsProject);
            debug('Loaded from GJS project data');
            verifyContent();
            return;
        } catch(e) {
            debug('GJS project parse failed: ' + e.message + ', falling back to HTML');
        }
    }

    // Otherwise load from HTML+CSS
    editor.setComponents(contentHtml);
    if (INIT_CSS) {
        editor.setStyle(INIT_CSS);
    }
    debug('Loaded from HTML+CSS');
    verifyContent();
}

function verifyContent() {
    setTimeout(function() {
        var wrapper = editor.DomComponents.getWrapper();
        var comps = wrapper ? wrapper.components().length : 0;
        var html = editor.getHtml();
        debug('Verify: components=' + comps + ', html_len=' + html.length);

        // Check if iframe body has visible content
        try {
            var frame = editor.Canvas.getFrameEl();
            if (frame && frame.contentDocument) {
                var body = frame.contentDocument.body;
                var bodyH = body ? body.scrollHeight : 0;
                var bodyW = body ? body.offsetWidth : 0;
                var wrapperEl = body ? body.querySelector('[data-gjs-type="wrapper"]') : null;
                var wrapH = wrapperEl ? wrapperEl.scrollHeight : 0;
                debug('Frame body: ' + bodyW + 'x' + bodyH + ', wrapper: ' + wrapH + 'px');

                if (wrapperEl && wrapH < 10) {
                    debug('WARNING: wrapper has zero height, forcing styles');
                    wrapperEl.style.cssText = 'min-height:100vh !important; position:relative !important; width:100% !important; display:block !important; background:#fff !important;';
                }

                // Check children visibility
                if (wrapperEl) {
                    var children = wrapperEl.children;
                    debug('Wrapper children: ' + children.length);
                    for (var i = 0; i < Math.min(children.length, 3); i++) {
                        var c = children[i];
                        var rect = c.getBoundingClientRect();
                        debug('  child[' + i + '] ' + c.tagName + ': ' + rect.width + 'x' + rect.height + ' vis=' + getComputedStyle(c).visibility);
                    }
                }
            }
        } catch(e) {
            debug('Frame inspect error: ' + e.message);
        }
    }, 500);
}

// Try multiple events to load content
editor.on('load', function() {
    debug('Event: editor load');
    loadContent();
});

editor.on('canvas:frame:load', function() {
    debug('Event: canvas:frame:load');
    loadContent();
});

// Extra safety: load after delay if events didn't fire
setTimeout(function() {
    if (!contentLoaded) {
        debug('Fallback: loading content via timeout');
        loadContent();
    }
}, 2000);

// ── Image upload handler ──
editor.on('asset:upload:start', function() { debug('Asset upload start'); });
editor.on('asset:upload:end', function() { debug('Asset upload end'); });
editor.on('asset:upload:error', function(err) { debug('Asset upload error: ' + err); });

// Custom upload for images
editor.setConfig && editor.setConfig({
    assetManager: {
        upload: '/admin/api/upload-file.php',
        uploadName: 'file',
        headers: { 'X-CSRF-Token': CSRF },
        params: { _csrf: CSRF, folder: 'pages' },
        autoAdd: true,
        multiUpload: false,
    }
});

// ── Save ──
document.getElementById('g_save').addEventListener('click', function() {
    var title = document.getElementById('g_title').value.trim();
    if (!title) { toast('Title required', true); return; }
    var slug = document.getElementById('g_slug').value.trim();
    if (!slug) slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    var gjsProject;
    try {
        gjsProject = JSON.stringify(editor.getProjectData());
    } catch(e) {
        gjsProject = '';
    }

    var fd = new FormData();
    fd.append('_csrf', CSRF);
    fd.append('title', title);
    fd.append('slug', slug);
    fd.append('locale', document.getElementById('g_locale').value);
    fd.append('status', document.getElementById('g_status').value);
    fd.append('html', editor.getHtml());
    fd.append('css', editor.getCss());
    fd.append('gjs_data', gjsProject);

    var url = PAGE_ID ? '/admin/page-builder-grapes.php?id=' + PAGE_ID : '/admin/page-builder-grapes.php';
    fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
        if (j.ok) { toast('Saved!'); if (j.redirect) setTimeout(function(){ location.href = j.redirect; }, 600); }
        else toast(j.error || 'Save failed', true);
    })
    .catch(function() { toast('Network error', true); });
});

// Ctrl+S
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('g_save').click();
    }
});

// ── Debug toggle ──
document.getElementById('g_debug_toggle').addEventListener('click', function() {
    var d = document.getElementById('gDebug');
    d.classList.toggle('visible');
});

})();
</script>
</body>
</html>
