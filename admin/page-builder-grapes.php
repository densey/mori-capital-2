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
body { font-family: 'Inter', system-ui, sans-serif; overflow: hidden; height: 100vh; display: flex; flex-direction: column; }

/* ── Top bar ── */
.gjs-topbar {
    background: #122842; color: #fff; height: 48px; flex-shrink: 0;
    display: flex; align-items: center; gap: 10px; padding: 0 14px;
    z-index: 100;
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

/* ── Editor wrapper — left panel + canvas + right panel ── */
.gjs-editor-wrap {
    flex: 1; display: flex; overflow: hidden;
}

/* ── Left toolbar (devices + view switchers) ── */
.gjs-left-bar {
    width: 40px; flex-shrink: 0; background: #1B3A5C;
    display: flex; flex-direction: column; align-items: center;
    padding: 8px 0; gap: 4px;
}
.gjs-left-bar button {
    width: 34px; height: 34px; background: transparent; border: none;
    color: rgba(255,255,255,.55); border-radius: 4px; cursor: pointer;
    font-size: 14px; display: flex; align-items: center; justify-content: center;
    transition: all .12s;
}
.gjs-left-bar button:hover { color: #fff; background: rgba(255,255,255,.08); }
.gjs-left-bar button.active { color: #1ABC9C; background: rgba(26,188,156,.15); }
.gjs-left-bar .sep { width: 24px; height: 1px; background: rgba(255,255,255,.12); margin: 6px 0; }

/* ── Right panel (blocks / layers / styles) ── */
.gjs-right-panel {
    width: 240px; flex-shrink: 0; background: #1B3A5C; overflow-y: auto;
    border-left: 1px solid rgba(255,255,255,.06);
}
.gjs-right-panel .panel-tab { display: none; }
.gjs-right-panel .panel-tab.active { display: block; }
.gjs-right-panel .panel-title {
    padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .08em; color: rgba(255,255,255,.4); border-bottom: 1px solid rgba(255,255,255,.06);
}

/* ── GrapesJS container ── */
#gjs-editor { flex: 1; overflow: hidden; }

/* ── GrapesJS theme overrides ── */
.gjs-one-bg { background-color: #1B3A5C !important; }
.gjs-two-color { color: #ddd !important; }
.gjs-three-bg { background-color: #122842 !important; }
.gjs-four-color, .gjs-four-color-h:hover { color: #1ABC9C !important; }
.gjs-cv-canvas { background: #E8ECF0 !important; }
.gjs-block { padding: 8px; min-height: 50px; border: 1px solid rgba(255,255,255,.08) !important; border-radius: 4px; }
.gjs-block:hover { border-color: #1ABC9C !important; }
.gjs-block__media { margin-bottom: 4px; }
.gjs-block-label { font-size: 11px !important; }
.gjs-blocks-c { padding: 8px; }
.gjs-category-title { font-size: 11px !important; font-weight: 700 !important; text-transform: uppercase !important; letter-spacing: .06em; }

/* Hide default GrapesJS panels — we build our own */
.gjs-pn-panels { display: none !important; }

/* ── Toast ── */
.gjs-toast {
    position: fixed; top: 56px; right: 16px; padding: 10px 18px; border-radius: 6px;
    font-size: 13px; font-weight: 600; z-index: 999; transform: translateY(-20px);
    opacity: 0; transition: all .2s ease; pointer-events: none;
    background: #1ABC9C; color: #fff; font-family: Inter, sans-serif;
}
.gjs-toast.show { transform: translateY(0); opacity: 1; }
.gjs-toast.err { background: #E74C3C; }
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
    <button class="tb tb-p" id="g_save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
</div>

<!-- Editor layout -->
<div class="gjs-editor-wrap">
    <!-- Left toolbar -->
    <div class="gjs-left-bar">
        <button class="active" data-panel="blocks" title="Blocks"><i class="fa-solid fa-th-large"></i></button>
        <button data-panel="layers" title="Layers"><i class="fa-solid fa-layer-group"></i></button>
        <button data-panel="styles" title="Styles"><i class="fa-solid fa-paint-brush"></i></button>
        <div class="sep"></div>
        <button data-device="Desktop" class="active" title="Desktop"><i class="fa-solid fa-desktop"></i></button>
        <button data-device="Tablet" title="Tablet"><i class="fa-solid fa-tablet-screen-button"></i></button>
        <button data-device="Mobile" title="Mobile"><i class="fa-solid fa-mobile-screen-button"></i></button>
        <div class="sep"></div>
        <button id="g_fullscreen" title="Fullscreen canvas"><i class="fa-solid fa-expand"></i></button>
        <button id="g_preview" title="Preview mode"><i class="fa-solid fa-eye"></i></button>
        <button id="g_undo" title="Undo"><i class="fa-solid fa-rotate-left"></i></button>
        <button id="g_redo" title="Redo"><i class="fa-solid fa-rotate-right"></i></button>
    </div>

    <!-- GrapesJS canvas -->
    <div id="gjs-editor"></div>

    <!-- Right panel -->
    <div class="gjs-right-panel">
        <div class="panel-tab active" id="panel-blocks">
            <div class="panel-title">Blocks</div>
            <div id="gjs-blocks"></div>
        </div>
        <div class="panel-tab" id="panel-layers">
            <div class="panel-title">Layers</div>
            <div id="gjs-layers"></div>
        </div>
        <div class="panel-tab" id="panel-styles">
            <div class="panel-title">Style Manager</div>
            <div id="gjs-styles"></div>
            <div id="gjs-traits" style="margin-top:8px;"></div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="gjs-toast" id="gToast"></div>

<script src="https://unpkg.com/grapesjs@0.21.13/dist/grapes.min.js"></script>
<script>
(function() {
'use strict';

var CSRF = <?= json_encode($csrfToken) ?>;
var PAGE_ID = <?= json_encode($id ?: null) ?>;
var INIT_HTML = <?= json_encode($existingHtml ?: '') ?>;
var INIT_CSS = <?= json_encode($existingCss ?: '') ?>;
var INIT_GJS = <?= json_encode($existingGjsData ?: '') ?>;

function toast(msg, err) {
    var el = document.getElementById('gToast');
    el.textContent = msg;
    el.className = 'gjs-toast show' + (err ? ' err' : '');
    setTimeout(function() { el.className = 'gjs-toast'; }, 2500);
}

// ── Default content ──
var defaultHtml = '<div class="container" style="max-width:1140px;margin:0 auto;padding:40px 15px;">' +
    '<h2>Page Title</h2>' +
    '<p>Start editing this page. Click on any text to edit, or drag blocks from the right panel.</p>' +
    '</div>';
var contentHtml = INIT_HTML && INIT_HTML.trim() ? INIT_HTML : defaultHtml;

// ── Protected CSS: base typography + Mori brand ──
var protectedCss = '' +
    '* { box-sizing: border-box; }\n' +
    'body { background: #fff !important; margin: 0; min-height: 100vh; font-family: Inter, "DM Sans", Arial, sans-serif; font-size: 15px; line-height: 1.65; color: #2C3E50; }\n' +
    '[data-gjs-type="wrapper"] { min-height: 100vh !important; overflow: visible !important; background: #fff; width: 100% !important; position: relative !important; }\n' +
    'h1 { font-size: 36px; color: #1B3A5C; font-weight: 700; margin: 0 0 .5em; line-height: 1.2; }\n' +
    'h2 { font-size: 28px; color: #1B3A5C; font-weight: 700; margin: 0 0 .5em; line-height: 1.25; }\n' +
    'h3 { font-size: 22px; color: #1B3A5C; font-weight: 700; margin: 0 0 .5em; line-height: 1.3; }\n' +
    'h4 { font-size: 18px; color: #1B3A5C; font-weight: 600; margin: 0 0 .5em; }\n' +
    'p { color: #5A6B7B; line-height: 1.7; margin: 0 0 1em; }\n' +
    'a { color: #1ABC9C; text-decoration: none; }\n' +
    'a:hover { color: #16A085; }\n' +
    'img { max-width: 100%; height: auto; }\n' +
    'ul, ol { color: #5A6B7B; padding-left: 24px; line-height: 1.8; }\n' +
    'blockquote { border-left: 3px solid #1ABC9C; padding: 12px 20px; margin: 0 0 1em; color: #1B3A5C; font-style: italic; background: #F8FFFE; border-radius: 0 4px 4px 0; }\n' +
    'hr { border: 0; border-top: 1px solid #E1E7EE; margin: 24px 0; }\n' +
    '.container { max-width: 1140px; margin: 0 auto; padding: 0 15px; }\n' +
    '.row { display: flex; flex-wrap: wrap; margin: 0 -15px; }\n' +
    '.col { flex: 1; padding: 0 15px; min-width: 0; }\n' +
    '.btn-default, .btn-mori { display: inline-block; padding: 12px 28px; background: #1ABC9C; color: #fff !important; border-radius: 4px; font-weight: 600; text-decoration: none; font-size: 14px; border: none; cursor: pointer; transition: background .15s; }\n' +
    '.btn-default:hover, .btn-mori:hover { background: #16A085; }\n' +
    '.btn-border { background: transparent; border: 2px solid #1ABC9C; color: #1ABC9C !important; }\n' +
    '.btn-border:hover { background: #1ABC9C; color: #fff !important; }\n' +
    '.text-center { text-align: center; }\n' +
    '.text-right { text-align: right; }\n' +
    '.bg-light { background: #F7F9FC; }\n' +
    '.bg-navy { background: #122842; color: #fff; }\n' +
    '.bg-navy h1,.bg-navy h2,.bg-navy h3,.bg-navy h4 { color: #fff; }\n' +
    '.bg-navy p { color: rgba(255,255,255,.75); }\n';

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
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap',
            '/css/bootstrap.min.css'
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
    blockManager: { appendTo: '#gjs-blocks' },
    layerManager: { appendTo: '#gjs-layers' },
    styleManager: {
        appendTo: '#gjs-styles',
        sectors: [
            { name: 'Layout', open: true, properties: [
                { property: 'display', type: 'select', options: [{value:'block'},{value:'flex'},{value:'inline-block'},{value:'none'}] },
                { property: 'flex-direction', type: 'select', options: [{value:'row'},{value:'column'}] },
                { property: 'justify-content', type: 'select', options: [{value:'flex-start'},{value:'center'},{value:'flex-end'},{value:'space-between'},{value:'space-around'}] },
                { property: 'align-items', type: 'select', options: [{value:'flex-start'},{value:'center'},{value:'flex-end'},{value:'stretch'}] },
                { property: 'gap' },
            ]},
            { name: 'Size', open: false, properties: [
                'width','min-width','max-width','height','min-height','max-height',
            ]},
            { name: 'Spacing', open: false, properties: [
                'padding','padding-top','padding-right','padding-bottom','padding-left',
                'margin','margin-top','margin-right','margin-bottom','margin-left',
            ]},
            { name: 'Typography', open: false, properties: [
                { property: 'font-family', type: 'select', options: [{value:'Inter, sans-serif',name:'Inter'},{value:'"DM Sans", sans-serif',name:'DM Sans'},{value:'Georgia, serif',name:'Georgia'},{value:'monospace',name:'Mono'}] },
                'font-size','font-weight','line-height','letter-spacing',
                { property: 'color', type: 'color' },
                { property: 'text-align', type: 'select', options: [{value:'left'},{value:'center'},{value:'right'},{value:'justify'}] },
                'text-decoration','text-transform',
            ]},
            { name: 'Background', open: false, properties: [
                { property: 'background-color', type: 'color' },
                'background-image','background-size','background-position','background-repeat',
            ]},
            { name: 'Border', open: false, properties: [
                'border','border-radius','border-top','border-bottom',
            ]},
            { name: 'Effects', open: false, properties: [
                'opacity','box-shadow',
                { property: 'overflow', type: 'select', options: [{value:'visible'},{value:'hidden'},{value:'auto'}] },
            ]},
        ]
    },
    traitManager: { appendTo: '#gjs-traits' },
    assetManager: {
        upload: '/admin/api/upload-file.php',
        uploadName: 'file',
        headers: { 'X-CSRF-Token': CSRF },
        params: { _csrf: CSRF, folder: 'pages' },
        autoAdd: true,
        multiUpload: false,
    }
});

// ── Add blocks ──
var bm = editor.BlockManager;

bm.add('section', { label: 'Section', category: 'Layout', media: '<i class="fa-solid fa-rectangle-list" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<section class="container" style="padding:40px 15px;"><h2>Section Title</h2><p>Section content goes here.</p></section>' });

bm.add('text', { label: 'Text', category: 'Basic', media: '<i class="fa-solid fa-paragraph" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<p>Insert your text here. Click to edit.</p>' });

bm.add('heading', { label: 'Heading', category: 'Basic', media: '<i class="fa-solid fa-heading" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<h2>Heading</h2>' });

bm.add('image', { label: 'Image', category: 'Basic', media: '<i class="fa-solid fa-image" style="font-size:28px;color:#1ABC9C"></i>',
    content: { type: 'image' }, activate: true });

bm.add('link-button', { label: 'Button', category: 'Basic', media: '<i class="fa-solid fa-square" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<a class="btn-default" href="#">Button Text</a>' });

bm.add('2-columns', { label: '2 Columns', category: 'Layout', media: '<i class="fa-solid fa-table-columns" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<div class="row"><div class="col"><p>Column 1</p></div><div class="col"><p>Column 2</p></div></div>' });

bm.add('3-columns', { label: '3 Columns', category: 'Layout', media: '<i class="fa-solid fa-table-cells" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<div class="row"><div class="col"><p>Column 1</p></div><div class="col"><p>Column 2</p></div><div class="col"><p>Column 3</p></div></div>' });

bm.add('divider', { label: 'Divider', category: 'Basic', media: '<i class="fa-solid fa-minus" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<hr>' });

bm.add('spacer', { label: 'Spacer', category: 'Basic', media: '<i class="fa-solid fa-arrows-up-down" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<div style="height:40px;"></div>' });

bm.add('quote', { label: 'Quote', category: 'Basic', media: '<i class="fa-solid fa-quote-left" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<blockquote><p>A meaningful quote goes here.</p></blockquote>' });

bm.add('list', { label: 'List', category: 'Basic', media: '<i class="fa-solid fa-list" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<ul><li>Item one</li><li>Item two</li><li>Item three</li></ul>' });

bm.add('video', { label: 'Video', category: 'Media', media: '<i class="fa-solid fa-video" style="font-size:28px;color:#1ABC9C"></i>',
    content: { type: 'video', src: 'https://www.youtube.com/embed/dQw4w9WgXcQ', style: { width: '100%', height: '350px' } } });

bm.add('hero-section', { label: 'Hero', category: 'Sections', media: '<i class="fa-solid fa-panorama" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<section class="bg-navy" style="padding:80px 30px;text-align:center;"><div class="container"><h1 style="color:#fff;font-size:42px;margin-bottom:16px;">Welcome to Mori Capital</h1><p style="color:rgba(255,255,255,.75);font-size:18px;max-width:650px;margin:0 auto 28px;">Specialist investor in Emerging European, Middle Eastern and African equity markets since 1998.</p><a class="btn-default" href="#">Learn More</a></div></section>' });

bm.add('cta-section', { label: 'CTA', category: 'Sections', media: '<i class="fa-solid fa-bullhorn" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<section class="bg-light" style="padding:50px 30px;text-align:center;"><div class="container"><h2>Get in Touch</h2><p style="max-width:600px;margin:0 auto 20px;">Contact us to learn more about our investment approach.</p><a class="btn-default" href="/contact.php">Contact Us</a></div></section>' });

bm.add('feature-grid', { label: 'Features', category: 'Sections', media: '<i class="fa-solid fa-grip-vertical" style="font-size:28px;color:#1ABC9C"></i>',
    content: '<section style="padding:50px 30px;"><div class="container"><div class="row" style="gap:30px;"><div class="col" style="text-align:center;"><h3 style="font-size:20px;">Feature 1</h3><p>Brief description of this feature.</p></div><div class="col" style="text-align:center;"><h3 style="font-size:20px;">Feature 2</h3><p>Brief description of this feature.</p></div><div class="col" style="text-align:center;"><h3 style="font-size:20px;">Feature 3</h3><p>Brief description of this feature.</p></div></div></div></section>' });

// ── Left bar: panel switching ──
document.querySelectorAll('.gjs-left-bar [data-panel]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.gjs-left-bar [data-panel]').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
        var panel = this.dataset.panel;
        document.querySelectorAll('.gjs-right-panel .panel-tab').forEach(function(p) { p.classList.toggle('active', p.id === 'panel-' + panel); });
    });
});

// ── Left bar: device switching ──
document.querySelectorAll('.gjs-left-bar [data-device]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.gjs-left-bar [data-device]').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
        editor.setDevice(this.dataset.device);
    });
});

// ── Toolbar buttons ──
document.getElementById('g_undo').addEventListener('click', function() { editor.UndoManager.undo(); });
document.getElementById('g_redo').addEventListener('click', function() { editor.UndoManager.redo(); });
document.getElementById('g_preview').addEventListener('click', function() { editor.runCommand('preview'); });
document.getElementById('g_fullscreen').addEventListener('click', function() {
    var el = document.querySelector('.gjs-editor-wrap');
    if (!document.fullscreenElement) el.requestFullscreen().catch(function(){}); else document.exitFullscreen();
});

// ── Auto-switch to styles panel on component select ──
editor.on('component:selected', function() {
    document.querySelectorAll('.gjs-left-bar [data-panel]').forEach(function(b) { b.classList.remove('active'); });
    var stylesBtn = document.querySelector('.gjs-left-bar [data-panel="styles"]');
    if (stylesBtn) stylesBtn.classList.add('active');
    document.querySelectorAll('.gjs-right-panel .panel-tab').forEach(function(p) { p.classList.toggle('active', p.id === 'panel-styles'); });
});

// ── Load content after frame is ready ──
var contentLoaded = false;
function loadContent() {
    if (contentLoaded) return;
    contentLoaded = true;
    if (INIT_GJS) {
        try {
            editor.loadProjectData(JSON.parse(INIT_GJS));
            console.log('[GJS] Loaded from project data');
            return;
        } catch(e) { console.warn('[GJS] Project data parse failed:', e); }
    }
    editor.setComponents(contentHtml);
    if (INIT_CSS) editor.setStyle(INIT_CSS);
    console.log('[GJS] Loaded from HTML, components:', editor.DomComponents.getWrapper().components().length);
}

editor.on('load', loadContent);
editor.on('canvas:frame:load', loadContent);
setTimeout(function() { if (!contentLoaded) loadContent(); }, 2500);

// ── Save ──
document.getElementById('g_save').addEventListener('click', function() {
    var title = document.getElementById('g_title').value.trim();
    if (!title) { toast('Title required', true); return; }
    var slug = document.getElementById('g_slug').value.trim();
    if (!slug) slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    var gjsProject = '';
    try { gjsProject = JSON.stringify(editor.getProjectData()); } catch(e) {}

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
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); document.getElementById('g_save').click(); }
});

})();
</script>
</body>
</html>
