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
    $blocksJson = $_POST['blocks_json'] ?? '';

    $body = '';
    if ($blocksJson) {
        $body = '<!--MORI_BLOCKS:' . base64_encode($blocksJson) . "-->\n";
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

// Parse existing body — supports new block format, legacy GrapesJS, and plain HTML
$existingHtml = '';
$existingBlocks = '';
if ($page && !empty($page['body'])) {
    $raw = $page['body'];
    if (preg_match('/^<!--MORI_BLOCKS:(.*?)-->\n?(.*)$/s', $raw, $m)) {
        $existingBlocks = base64_decode($m[1]) ?: '';
        $existingHtml = $m[2];
    } elseif (preg_match('/^<style>(.*?)<\/style>(.*)$/s', $raw, $m)) {
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
<title><?= e($pageTitle) ?> — Visual Builder</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
:root {
    --pb-navy: #122842;
    --pb-navy-light: #1B3A5C;
    --pb-teal: #1ABC9C;
    --pb-teal-dark: #16A085;
    --pb-text: #2C3E50;
    --pb-muted: #7A8B99;
    --pb-border: #E1E7EE;
    --pb-bg: #F7F9FC;
    --pb-danger: #E74C3C;
    --pb-panel-w: 260px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', system-ui, sans-serif; background: var(--pb-bg); color: var(--pb-text); font-size: 14px; overflow: hidden; height: 100vh; }

/* ── Top bar ── */
.pb-topbar {
    background: var(--pb-navy); color: #fff; height: 52px;
    display: flex; align-items: center; gap: 10px; padding: 0 16px;
    border-bottom: 1px solid rgba(255,255,255,.08); z-index: 100; position: relative;
}
.pb-topbar input, .pb-topbar select {
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15);
    color: #fff; padding: 6px 10px; border-radius: 4px; font: inherit; font-size: 13px;
}
.pb-topbar input:focus, .pb-topbar select:focus { outline: none; border-color: var(--pb-teal); }
.pb-topbar input::placeholder { color: rgba(255,255,255,.4); }
.pb-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 4px; font: inherit; font-size: 13px;
    font-weight: 600; cursor: pointer; border: none; text-decoration: none;
    transition: all .15s ease;
}
.pb-btn-primary { background: var(--pb-teal); color: #fff; }
.pb-btn-primary:hover { background: var(--pb-teal-dark); }
.pb-btn-ghost { background: transparent; color: rgba(255,255,255,.8); border: 1px solid rgba(255,255,255,.2); }
.pb-btn-ghost:hover { background: rgba(255,255,255,.06); color: #fff; }
.pb-topbar .spacer { flex: 1; }

/* ── Layout ── */
.pb-layout { display: flex; height: calc(100vh - 52px); }

/* ── Left panel ── */
.pb-panel {
    width: var(--pb-panel-w); flex-shrink: 0; background: #fff;
    border-right: 1px solid var(--pb-border); display: flex; flex-direction: column;
    overflow-y: auto;
}
.pb-panel-header {
    padding: 14px 16px; font-weight: 700; font-size: 12px;
    text-transform: uppercase; letter-spacing: .08em; color: var(--pb-muted);
    border-bottom: 1px solid var(--pb-border); display: flex; align-items: center;
    justify-content: space-between;
}
.pb-panel-header .pb-back-btn {
    background: none; border: none; color: var(--pb-teal); cursor: pointer;
    font-size: 13px; font-weight: 600; display: none;
}
.pb-panel-header .pb-back-btn.visible { display: inline-flex; align-items: center; gap: 4px; }

/* ── Block palette ── */
.pb-palette { padding: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.pb-palette-item {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 6px; padding: 14px 8px; border: 1px solid var(--pb-border); border-radius: 6px;
    cursor: grab; background: #fff; font-size: 11px; font-weight: 600;
    color: var(--pb-muted); transition: all .15s ease; user-select: none;
}
.pb-palette-item:hover { border-color: var(--pb-teal); color: var(--pb-teal); background: #F0FBF9; }
.pb-palette-item:active { cursor: grabbing; }
.pb-palette-item i { font-size: 18px; }

/* ── Settings panel ── */
.pb-settings { padding: 16px; display: none; flex-direction: column; gap: 14px; }
.pb-settings.active { display: flex; }
.pb-settings label { font-size: 12px; font-weight: 600; color: var(--pb-navy); display: block; margin-bottom: 4px; }
.pb-settings input, .pb-settings select, .pb-settings textarea {
    width: 100%; padding: 7px 10px; border: 1px solid var(--pb-border); border-radius: 4px;
    font: inherit; font-size: 13px; color: var(--pb-text);
}
.pb-settings input:focus, .pb-settings select:focus, .pb-settings textarea:focus { outline: none; border-color: var(--pb-teal); }
.pb-settings textarea { min-height: 120px; resize: vertical; font-family: 'Courier New', monospace; font-size: 12px; }
.pb-settings .pb-setting-group { }
.pb-settings .pb-align-btns { display: flex; gap: 4px; }
.pb-settings .pb-align-btns button {
    flex: 1; padding: 6px; border: 1px solid var(--pb-border); background: #fff;
    border-radius: 4px; cursor: pointer; font-size: 13px; color: var(--pb-muted);
}
.pb-settings .pb-align-btns button.active { background: var(--pb-teal); color: #fff; border-color: var(--pb-teal); }
.pb-settings .pb-delete-block {
    margin-top: 12px; padding: 8px; background: #FDF2F2; color: var(--pb-danger);
    border: 1px solid #FECACA; border-radius: 4px; cursor: pointer;
    font: inherit; font-size: 12px; font-weight: 600; text-align: center;
}
.pb-settings .pb-delete-block:hover { background: #FEE2E2; }

/* ── Canvas ── */
.pb-canvas {
    flex: 1; overflow-y: auto; padding: 32px;
    background: var(--pb-bg);
}
.pb-canvas-inner {
    max-width: 900px; margin: 0 auto; min-height: 400px;
    background: #fff; border-radius: 8px; border: 1px solid var(--pb-border);
    box-shadow: 0 4px 24px rgba(0,0,0,.06); padding: 0;
    position: relative;
}

/* ── Block wrapper ── */
.pb-block {
    position: relative; border: 2px solid transparent; border-radius: 4px;
    transition: border-color .15s; cursor: default;
}
.pb-block:hover { border-color: #D1E9FF; }
.pb-block.selected { border-color: var(--pb-teal); }
.pb-block.sortable-ghost { opacity: .4; border-color: var(--pb-teal); border-style: dashed; }
.pb-block.sortable-drag { box-shadow: 0 8px 32px rgba(0,0,0,.15); }

/* Block toolbar (visible on hover/select) */
.pb-block-handle {
    position: absolute; top: -1px; left: -1px; right: -1px;
    display: flex; align-items: center; gap: 1px;
    opacity: 0; transition: opacity .15s; z-index: 5;
    pointer-events: none;
}
.pb-block:hover .pb-block-handle, .pb-block.selected .pb-block-handle { opacity: 1; pointer-events: auto; }
.pb-block-handle .label {
    background: var(--pb-teal); color: #fff; font-size: 10px; font-weight: 700;
    padding: 2px 8px; border-radius: 4px 0 0 0; letter-spacing: .03em;
    text-transform: uppercase;
}
.pb-block-handle .actions {
    margin-left: auto; display: flex; gap: 1px;
}
.pb-block-handle .actions button {
    background: var(--pb-navy); color: rgba(255,255,255,.85); border: none;
    width: 26px; height: 22px; cursor: pointer; font-size: 11px;
    display: inline-flex; align-items: center; justify-content: center;
}
.pb-block-handle .actions button:hover { background: var(--pb-teal); color: #fff; }
.pb-block-handle .actions button:last-child { border-radius: 0 4px 0 0; }

/* Block content area */
.pb-block-body { padding: 20px 28px; min-height: 40px; }
.pb-block-body:focus { outline: none; }
.pb-block-body h1, .pb-block-body h2, .pb-block-body h3, .pb-block-body h4 {
    color: var(--pb-navy); font-weight: 700; line-height: 1.3; margin: 0;
}
.pb-block-body h1 { font-size: 32px; }
.pb-block-body h2 { font-size: 26px; }
.pb-block-body h3 { font-size: 20px; }
.pb-block-body h4 { font-size: 17px; }
.pb-block-body p { color: #5A6B7B; line-height: 1.7; margin: 0 0 .8em; font-size: 15px; }
.pb-block-body p:last-child { margin-bottom: 0; }
.pb-block-body a { color: var(--pb-teal); }
.pb-block-body blockquote {
    border-left: 3px solid var(--pb-teal); padding: 12px 20px; margin: 0;
    background: #F8FFFE; color: var(--pb-navy); font-style: italic; border-radius: 0 4px 4px 0;
}
.pb-block-body ul, .pb-block-body ol { padding-left: 24px; color: #5A6B7B; line-height: 1.8; }
.pb-block-body img { max-width: 100%; height: auto; border-radius: 4px; display: block; }
.pb-block-body .btn-mori {
    display: inline-block; padding: 12px 28px; background: var(--pb-teal); color: #fff;
    border-radius: 4px; font-weight: 600; text-decoration: none; font-size: 14px;
}
.pb-block-body .btn-mori.outline { background: transparent; border: 2px solid var(--pb-teal); color: var(--pb-teal); }
.pb-block-body .pb-spacer { }
.pb-block-body hr { border: none; border-top: 1px solid var(--pb-border); margin: 0; }
.pb-block-body .pb-columns { display: flex; gap: 20px; }
.pb-block-body .pb-col { flex: 1; min-width: 0; }
.pb-block-body .pb-col [contenteditable] { min-height: 60px; padding: 8px; border: 1px dashed var(--pb-border); border-radius: 4px; }
.pb-block-body .pb-col [contenteditable]:focus { border-color: var(--pb-teal); outline: none; }
.pb-block-body .pb-video-wrap { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 4px; }
.pb-block-body .pb-video-wrap iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; }
.pb-block-body .pb-html-preview { padding: 12px; background: #F8F9FA; border-radius: 4px; border: 1px dashed var(--pb-border); font-size: 13px; }

/* ── Add block button ── */
.pb-add-row {
    padding: 16px 28px; text-align: center;
}
.pb-add-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 24px; border: 2px dashed var(--pb-border); border-radius: 6px;
    background: transparent; color: var(--pb-muted); cursor: pointer;
    font: inherit; font-size: 13px; font-weight: 600; transition: all .15s;
}
.pb-add-btn:hover { border-color: var(--pb-teal); color: var(--pb-teal); background: #F0FBF9; }

/* ── Insert line between blocks ── */
.pb-insert-line {
    height: 20px; position: relative; cursor: pointer; opacity: 0; transition: opacity .15s;
}
.pb-canvas-inner:hover .pb-insert-line { opacity: 1; }
.pb-insert-line::after {
    content: '+'; position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
    width: 22px; height: 22px; border-radius: 50%; background: var(--pb-teal); color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700;
    opacity: 0; transition: opacity .15s;
}
.pb-insert-line:hover::after { opacity: 1; }
.pb-insert-line::before {
    content: ''; position: absolute; top: 50%; left: 28px; right: 28px;
    height: 2px; background: var(--pb-teal); opacity: 0; transition: opacity .15s;
}
.pb-insert-line:hover::before { opacity: .3; }

/* ── Formatting toolbar ── */
.pb-fmt {
    position: fixed; display: none; background: var(--pb-navy); border-radius: 6px;
    padding: 4px; box-shadow: 0 4px 16px rgba(0,0,0,.2); z-index: 200;
    gap: 2px;
}
.pb-fmt.visible { display: flex; }
.pb-fmt button {
    background: transparent; border: none; color: rgba(255,255,255,.85);
    width: 32px; height: 30px; border-radius: 4px; cursor: pointer;
    font-size: 13px; display: inline-flex; align-items: center; justify-content: center;
}
.pb-fmt button:hover { background: rgba(255,255,255,.12); color: #fff; }
.pb-fmt button.active { background: var(--pb-teal); color: #fff; }
.pb-fmt .sep { width: 1px; background: rgba(255,255,255,.15); margin: 4px 2px; }

/* ── Image upload overlay ── */
.pb-img-upload {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    min-height: 160px; border: 2px dashed var(--pb-border); border-radius: 8px;
    background: #FAFBFC; cursor: pointer; gap: 8px; color: var(--pb-muted);
    transition: all .15s;
}
.pb-img-upload:hover { border-color: var(--pb-teal); color: var(--pb-teal); }
.pb-img-upload i { font-size: 32px; }
.pb-img-upload span { font-size: 13px; font-weight: 600; }
.pb-img-upload.has-image { border: none; background: transparent; }

/* ── Toast ── */
.pb-toast {
    position: fixed; top: 60px; right: 20px; padding: 10px 20px; border-radius: 6px;
    font-size: 13px; font-weight: 600; z-index: 300; transform: translateY(-20px);
    opacity: 0; transition: all .2s ease; pointer-events: none;
    background: var(--pb-teal); color: #fff;
}
.pb-toast.show { transform: translateY(0); opacity: 1; }
.pb-toast.err { background: var(--pb-danger); }

/* ── Empty state ── */
.pb-empty {
    text-align: center; padding: 60px 30px; color: var(--pb-muted);
}
.pb-empty i { font-size: 48px; margin-bottom: 16px; display: block; color: var(--pb-border); }
.pb-empty p { font-size: 14px; margin-bottom: 20px; }
</style>
</head>
<body>

<!-- Top bar -->
<div class="pb-topbar">
    <a href="/admin/pages.php" class="pb-btn pb-btn-ghost"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <input type="text" id="pb_title" placeholder="Page title" value="<?= e($page['title'] ?? '') ?>" style="flex:1;min-width:140px;max-width:300px">
    <input type="text" id="pb_slug" placeholder="slug" value="<?= e($page['slug'] ?? '') ?>" style="width:120px">
    <select id="pb_locale">
        <option value="en" <?= ($page['locale'] ?? 'en')==='en'?'selected':'' ?>>EN</option>
        <option value="de" <?= ($page['locale'] ?? '')==='de'?'selected':'' ?>>DE</option>
    </select>
    <select id="pb_status">
        <option value="draft" <?= ($page['status'] ?? 'draft')==='draft'?'selected':'' ?>>Draft</option>
        <option value="published" <?= ($page['status'] ?? '')==='published'?'selected':'' ?>>Published</option>
    </select>
    <span class="spacer"></span>
    <?php if (!$isNew): ?>
    <a href="/admin/page-edit.php?id=<?= $id ?>" class="pb-btn pb-btn-ghost"><i class="fa-solid fa-pen"></i> Classic</a>
    <?php endif; ?>
    <button class="pb-btn pb-btn-ghost" id="pb_preview" title="Preview"><i class="fa-solid fa-eye"></i> Preview</button>
    <button class="pb-btn pb-btn-primary" id="pb_save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
</div>

<div class="pb-layout">
    <!-- Left panel -->
    <div class="pb-panel">
        <div class="pb-panel-header">
            <span id="panelTitle">Add Blocks</span>
            <button class="pb-back-btn" id="panelBack" onclick="showPalette()"><i class="fa-solid fa-arrow-left"></i> Back</button>
        </div>
        <!-- Block palette -->
        <div class="pb-palette" id="blockPalette">
            <div class="pb-palette-item" data-type="heading"><i class="fa-solid fa-heading"></i>Heading</div>
            <div class="pb-palette-item" data-type="text"><i class="fa-solid fa-paragraph"></i>Text</div>
            <div class="pb-palette-item" data-type="image"><i class="fa-solid fa-image"></i>Image</div>
            <div class="pb-palette-item" data-type="button"><i class="fa-solid fa-square"></i>Button</div>
            <div class="pb-palette-item" data-type="columns"><i class="fa-solid fa-columns"></i>Columns</div>
            <div class="pb-palette-item" data-type="spacer"><i class="fa-solid fa-arrows-up-down"></i>Spacer</div>
            <div class="pb-palette-item" data-type="divider"><i class="fa-solid fa-minus"></i>Divider</div>
            <div class="pb-palette-item" data-type="quote"><i class="fa-solid fa-quote-left"></i>Quote</div>
            <div class="pb-palette-item" data-type="list"><i class="fa-solid fa-list"></i>List</div>
            <div class="pb-palette-item" data-type="video"><i class="fa-solid fa-video"></i>Video</div>
            <div class="pb-palette-item" data-type="html"><i class="fa-solid fa-code"></i>HTML</div>
            <div class="pb-palette-item" data-type="section"><i class="fa-solid fa-rectangle-list"></i>Section</div>
        </div>
        <!-- Settings panel (hidden until a block is selected) -->
        <div class="pb-settings" id="blockSettings"></div>
    </div>

    <!-- Canvas -->
    <div class="pb-canvas" id="canvas">
        <div class="pb-canvas-inner" id="canvasInner">
            <!-- Blocks render here -->
        </div>
    </div>
</div>

<!-- Formatting toolbar -->
<div class="pb-fmt" id="fmtBar">
    <button data-cmd="bold" title="Bold"><i class="fa-solid fa-bold"></i></button>
    <button data-cmd="italic" title="Italic"><i class="fa-solid fa-italic"></i></button>
    <button data-cmd="underline" title="Underline"><i class="fa-solid fa-underline"></i></button>
    <div class="sep"></div>
    <button data-cmd="createLink" title="Link"><i class="fa-solid fa-link"></i></button>
    <button data-cmd="unlink" title="Remove link"><i class="fa-solid fa-link-slash"></i></button>
    <div class="sep"></div>
    <button data-cmd="removeFormat" title="Clear formatting"><i class="fa-solid fa-eraser"></i></button>
</div>

<!-- Toast -->
<div class="pb-toast" id="pbToast"></div>

<!-- Hidden file input -->
<input type="file" id="fileInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
(function() {
'use strict';

var CSRF = <?= json_encode($csrfToken) ?>;
var PAGE_ID = <?= json_encode($id ?: null) ?>;
var INIT_BLOCKS_JSON = <?= json_encode($existingBlocks) ?>;
var INIT_HTML = <?= json_encode($existingHtml) ?>;

// ── Block type definitions ──
var TYPES = {
    heading: { label: 'Heading', icon: 'fa-heading', defaults: { text: 'Heading', level: 2, align: 'left' } },
    text:    { label: 'Text',    icon: 'fa-paragraph', defaults: { html: '<p>Start typing your content here...</p>', align: 'left' } },
    image:   { label: 'Image',   icon: 'fa-image', defaults: { src: '', alt: '', width: '100', align: 'center' } },
    button:  { label: 'Button',  icon: 'fa-square', defaults: { text: 'Learn More', url: '#', style: 'primary', align: 'center' } },
    columns: { label: 'Columns', icon: 'fa-columns', defaults: { count: 2, cols: ['<p>Column 1</p>', '<p>Column 2</p>'] } },
    spacer:  { label: 'Spacer',  icon: 'fa-arrows-up-down', defaults: { height: 40 } },
    divider: { label: 'Divider', icon: 'fa-minus', defaults: { style: 'solid', color: '#E1E7EE' } },
    quote:   { label: 'Quote',   icon: 'fa-quote-left', defaults: { text: 'A meaningful quote goes here.', author: '' } },
    list:    { label: 'List',    icon: 'fa-list', defaults: { html: '<ul><li>Item one</li><li>Item two</li><li>Item three</li></ul>' } },
    video:   { label: 'Video',   icon: 'fa-video', defaults: { url: '' } },
    html:    { label: 'HTML',    icon: 'fa-code', defaults: { code: '<div>\n  Custom HTML here\n</div>' } },
    section: { label: 'Section', icon: 'fa-rectangle-list', defaults: { bgColor: '#F7F9FC', textColor: '#2C3E50', padding: 40, html: '<h3>Section Title</h3><p>Section content goes here.</p>' } }
};

// ── State ──
var blocks = [];
var selectedId = null;
var uid = 0;
function genId() { return 'b' + (++uid) + '_' + Math.random().toString(36).substr(2, 5); }

// ── Initialize blocks ──
function initBlocks() {
    if (INIT_BLOCKS_JSON) {
        try {
            blocks = JSON.parse(INIT_BLOCKS_JSON);
            if (!Array.isArray(blocks)) blocks = [];
        } catch(e) { blocks = []; }
    }
    if (blocks.length === 0 && INIT_HTML && INIT_HTML.trim()) {
        blocks = [{ id: genId(), type: 'html', data: { code: INIT_HTML } }];
    }
    if (blocks.length === 0) {
        blocks = [
            { id: genId(), type: 'heading', data: { text: 'Page Title', level: 1, align: 'left' } },
            { id: genId(), type: 'text', data: { html: '<p>Start building your page by adding blocks from the left panel, or click here to start typing.</p>', align: 'left' } }
        ];
    }
    renderAll();
}

// ── Render all blocks ──
function renderAll() {
    var el = document.getElementById('canvasInner');
    el.innerHTML = '';
    if (blocks.length === 0) {
        el.innerHTML = '<div class="pb-empty"><i class="fa-solid fa-shapes"></i><p>No blocks yet. Drag a block from the left panel or click below to add one.</p></div>';
    }
    blocks.forEach(function(b, i) {
        if (i > 0) el.appendChild(makeInsertLine(i));
        el.appendChild(renderBlock(b));
    });
    var addRow = document.createElement('div');
    addRow.className = 'pb-add-row';
    addRow.innerHTML = '<button class="pb-add-btn" onclick="showAddMenu()"><i class="fa-solid fa-plus"></i> Add Block</button>';
    el.appendChild(addRow);
    initSortable();
    if (selectedId) {
        var sel = el.querySelector('[data-block-id="' + selectedId + '"]');
        if (sel) sel.classList.add('selected');
    }
}

// ── Render a single block ──
function renderBlock(block) {
    var wrap = document.createElement('div');
    wrap.className = 'pb-block' + (block.id === selectedId ? ' selected' : '');
    wrap.dataset.blockId = block.id;
    wrap.dataset.blockType = block.type;

    var typeDef = TYPES[block.type] || { label: block.type, icon: 'fa-cube' };

    // Handle bar
    var handle = document.createElement('div');
    handle.className = 'pb-block-handle';
    handle.innerHTML = '<span class="label">' + typeDef.label + '</span>' +
        '<span class="actions">' +
        '<button title="Move up" data-act="up"><i class="fa-solid fa-chevron-up"></i></button>' +
        '<button title="Move down" data-act="down"><i class="fa-solid fa-chevron-down"></i></button>' +
        '<button title="Duplicate" data-act="dup"><i class="fa-solid fa-clone"></i></button>' +
        '<button title="Delete" data-act="del"><i class="fa-solid fa-trash"></i></button>' +
        '</span>';
    wrap.appendChild(handle);

    // Block body
    var body = document.createElement('div');
    body.className = 'pb-block-body';
    body.innerHTML = renderBlockContent(block);
    wrap.appendChild(body);

    // Events
    wrap.addEventListener('click', function(e) {
        if (e.target.closest('.pb-block-handle')) return;
        selectBlock(block.id);
    });
    handle.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-act]');
        if (!btn) return;
        e.stopPropagation();
        var act = btn.dataset.act;
        if (act === 'up') moveBlock(block.id, -1);
        else if (act === 'down') moveBlock(block.id, 1);
        else if (act === 'dup') duplicateBlock(block.id);
        else if (act === 'del') deleteBlock(block.id);
    });

    // Setup contenteditable after insert
    setTimeout(function() { setupEditable(wrap, block); }, 0);

    return wrap;
}

// ── Render block inner content ──
function renderBlockContent(b) {
    var d = b.data;
    switch(b.type) {
        case 'heading':
            var tag = 'h' + (d.level || 2);
            return '<' + tag + ' contenteditable="true" style="text-align:' + (d.align||'left') + '">' + esc(d.text) + '</' + tag + '>';
        case 'text':
            return '<div contenteditable="true" style="text-align:' + (d.align||'left') + '">' + (d.html || '') + '</div>';
        case 'image':
            if (!d.src) return '<div class="pb-img-upload" data-action="upload"><i class="fa-solid fa-cloud-arrow-up"></i><span>Click to upload image</span></div>';
            return '<div style="text-align:' + (d.align||'center') + '"><img src="' + esc(d.src) + '" alt="' + esc(d.alt||'') + '" style="max-width:' + (d.width||100) + '%;height:auto"></div>';
        case 'button':
            return '<div style="text-align:' + (d.align||'center') + '"><a class="btn-mori' + (d.style==='outline'?' outline':'') + '" href="' + esc(d.url||'#') + '">' + esc(d.text) + '</a></div>';
        case 'columns':
            var n = d.count || 2;
            var cols = d.cols || [];
            var html = '<div class="pb-columns">';
            for (var i = 0; i < n; i++) {
                html += '<div class="pb-col"><div contenteditable="true" data-col="' + i + '">' + (cols[i] || '<p>Column ' + (i+1) + '</p>') + '</div></div>';
            }
            return html + '</div>';
        case 'spacer':
            return '<div class="pb-spacer" style="height:' + (d.height||40) + 'px;background:repeating-linear-gradient(45deg,transparent,transparent 4px,rgba(0,0,0,.03) 4px,rgba(0,0,0,.03) 8px)"></div>';
        case 'divider':
            return '<hr style="border-top:1px ' + (d.style||'solid') + ' ' + (d.color||'#E1E7EE') + '">';
        case 'quote':
            return '<blockquote><p contenteditable="true">' + esc(d.text) + '</p>' + (d.author ? '<footer>— ' + esc(d.author) + '</footer>' : '') + '</blockquote>';
        case 'list':
            return '<div contenteditable="true">' + (d.html || '<ul><li>Item</li></ul>') + '</div>';
        case 'video':
            if (!d.url) return '<div class="pb-img-upload" style="min-height:120px"><i class="fa-solid fa-video"></i><span>Set video URL in settings</span></div>';
            var embedUrl = parseVideoUrl(d.url);
            return '<div class="pb-video-wrap"><iframe src="' + esc(embedUrl) + '" allowfullscreen></iframe></div>';
        case 'html':
            return '<div class="pb-html-preview">' + (d.code || '') + '</div>';
        case 'section':
            return '<div style="background:' + (d.bgColor||'#F7F9FC') + ';color:' + (d.textColor||'#2C3E50') + ';padding:' + (d.padding||40) + 'px 28px;margin:-20px -28px;border-radius:4px"><div contenteditable="true">' + (d.html || '') + '</div></div>';
        default:
            return '<p>[Unknown block type: ' + b.type + ']</p>';
    }
}

// ── Setup contenteditable sync ──
function setupEditable(wrap, block) {
    var editables = wrap.querySelectorAll('[contenteditable="true"]');
    editables.forEach(function(el) {
        el.addEventListener('input', function() { syncEditable(block, wrap); });
        el.addEventListener('focus', function() { selectBlock(block.id, true); });
        el.addEventListener('mouseup', checkFormatBar);
        el.addEventListener('keyup', checkFormatBar);
    });
    var uploadArea = wrap.querySelector('[data-action="upload"]');
    if (uploadArea) {
        uploadArea.addEventListener('click', function() { triggerUpload(block.id); });
    }
}

function syncEditable(block, wrap) {
    switch(block.type) {
        case 'heading':
            var h = wrap.querySelector('[contenteditable]');
            if (h) block.data.text = h.textContent;
            break;
        case 'text':
        case 'list':
            var d = wrap.querySelector('[contenteditable]');
            if (d) block.data.html = d.innerHTML;
            break;
        case 'quote':
            var q = wrap.querySelector('[contenteditable]');
            if (q) block.data.text = q.textContent;
            break;
        case 'columns':
            var cols = wrap.querySelectorAll('[data-col]');
            block.data.cols = [];
            cols.forEach(function(c) { block.data.cols.push(c.innerHTML); });
            break;
        case 'section':
            var s = wrap.querySelector('[contenteditable]');
            if (s) block.data.html = s.innerHTML;
            break;
    }
}

// ── Block operations ──
function selectBlock(id, skipPanel) {
    selectedId = id;
    document.querySelectorAll('.pb-block').forEach(function(el) {
        el.classList.toggle('selected', el.dataset.blockId === id);
    });
    if (!skipPanel) showSettings(id);
}

function findIndex(id) {
    for (var i = 0; i < blocks.length; i++) { if (blocks[i].id === id) return i; }
    return -1;
}

function moveBlock(id, dir) {
    var i = findIndex(id);
    var j = i + dir;
    if (j < 0 || j >= blocks.length) return;
    var tmp = blocks[i]; blocks[i] = blocks[j]; blocks[j] = tmp;
    renderAll();
}

function duplicateBlock(id) {
    var i = findIndex(id);
    if (i < 0) return;
    var clone = JSON.parse(JSON.stringify(blocks[i]));
    clone.id = genId();
    blocks.splice(i + 1, 0, clone);
    selectedId = clone.id;
    renderAll();
    showSettings(clone.id);
}

function deleteBlock(id) {
    var i = findIndex(id);
    if (i < 0) return;
    blocks.splice(i, 1);
    if (selectedId === id) { selectedId = null; showPalette(); }
    renderAll();
}

function addBlock(type, atIndex) {
    var def = TYPES[type];
    if (!def) return;
    var block = { id: genId(), type: type, data: JSON.parse(JSON.stringify(def.defaults)) };
    if (typeof atIndex === 'number') blocks.splice(atIndex, 0, block);
    else blocks.push(block);
    selectedId = block.id;
    renderAll();
    showSettings(block.id);
    var el = document.querySelector('[data-block-id="' + block.id + '"]');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// ── Insert line ──
function makeInsertLine(insertIndex) {
    var line = document.createElement('div');
    line.className = 'pb-insert-line';
    line.addEventListener('click', function() {
        showAddMenuAt(insertIndex);
    });
    return line;
}

// ── Add menu (shows palette, clicking adds at end or specific index) ──
var pendingInsertIndex = null;
window.showAddMenu = function() { pendingInsertIndex = null; showPalette(); };
function showAddMenuAt(idx) { pendingInsertIndex = idx; showPalette(); }

// ── Panel: palette vs settings ──
function showPalette() {
    document.getElementById('blockPalette').style.display = 'grid';
    document.getElementById('blockSettings').className = 'pb-settings';
    document.getElementById('panelTitle').textContent = pendingInsertIndex !== null ? 'Insert Block' : 'Add Blocks';
    document.getElementById('panelBack').className = 'pb-back-btn' + (selectedId ? ' visible' : '');
}

function showSettings(id) {
    var block = blocks[findIndex(id)];
    if (!block) return;
    var typeDef = TYPES[block.type] || {};
    document.getElementById('blockPalette').style.display = 'none';
    document.getElementById('panelTitle').textContent = typeDef.label + ' Settings';
    document.getElementById('panelBack').className = 'pb-back-btn visible';

    var panel = document.getElementById('blockSettings');
    panel.className = 'pb-settings active';
    panel.innerHTML = renderSettingsFor(block);
    bindSettings(panel, block);
}

function renderSettingsFor(block) {
    var d = block.data;
    var h = '';
    switch(block.type) {
        case 'heading':
            h += settingSelect('Level', 'level', d.level, [{v:1,l:'H1'},{v:2,l:'H2'},{v:3,l:'H3'},{v:4,l:'H4'}]);
            h += settingAlign(d.align);
            break;
        case 'text':
            h += settingAlign(d.align);
            break;
        case 'image':
            if (d.src) h += '<div class="pb-setting-group"><img src="' + esc(d.src) + '" style="max-width:100%;border-radius:4px;margin-bottom:8px"></div>';
            h += '<div class="pb-setting-group"><label>Image</label><button class="pb-btn pb-btn-primary" style="width:100%" data-action="upload-img"><i class="fa-solid fa-cloud-arrow-up"></i> ' + (d.src ? 'Change Image' : 'Upload Image') + '</button></div>';
            h += '<div class="pb-setting-group"><label>Alt Text</label><input type="text" data-key="alt" value="' + esc(d.alt||'') + '"></div>';
            h += settingSelect('Width', 'width', d.width||'100', [{v:'100',l:'Full'},{v:'75',l:'75%'},{v:'50',l:'50%'},{v:'33',l:'33%'}]);
            h += settingAlign(d.align);
            break;
        case 'button':
            h += '<div class="pb-setting-group"><label>Button Text</label><input type="text" data-key="text" value="' + esc(d.text) + '"></div>';
            h += '<div class="pb-setting-group"><label>URL</label><input type="text" data-key="url" value="' + esc(d.url||'') + '"></div>';
            h += settingSelect('Style', 'style', d.style, [{v:'primary',l:'Filled'},{v:'outline',l:'Outline'}]);
            h += settingAlign(d.align);
            break;
        case 'columns':
            h += settingSelect('Columns', 'count', d.count, [{v:2,l:'2 Columns'},{v:3,l:'3 Columns'},{v:4,l:'4 Columns'}]);
            break;
        case 'spacer':
            h += '<div class="pb-setting-group"><label>Height (px)</label><input type="range" data-key="height" min="10" max="200" value="' + (d.height||40) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'"><span style="font-size:12px;color:#7A8B99">' + (d.height||40) + 'px</span></div>';
            break;
        case 'divider':
            h += settingSelect('Line Style', 'style', d.style, [{v:'solid',l:'Solid'},{v:'dashed',l:'Dashed'},{v:'dotted',l:'Dotted'}]);
            h += '<div class="pb-setting-group"><label>Color</label><input type="color" data-key="color" value="' + (d.color||'#E1E7EE') + '" style="width:100%;height:36px;padding:2px"></div>';
            break;
        case 'quote':
            h += '<div class="pb-setting-group"><label>Author</label><input type="text" data-key="author" value="' + esc(d.author||'') + '" placeholder="Optional author name"></div>';
            break;
        case 'video':
            h += '<div class="pb-setting-group"><label>Video URL</label><input type="text" data-key="url" value="' + esc(d.url||'') + '" placeholder="YouTube or Vimeo URL"><small style="font-size:11px;color:#7A8B99;margin-top:4px;display:block">Paste a YouTube or Vimeo link</small></div>';
            break;
        case 'html':
            h += '<div class="pb-setting-group"><label>HTML Code</label><textarea data-key="code">' + esc(d.code||'') + '</textarea></div>';
            break;
        case 'section':
            h += '<div class="pb-setting-group"><label>Background Color</label><input type="color" data-key="bgColor" value="' + (d.bgColor||'#F7F9FC') + '" style="width:100%;height:36px;padding:2px"></div>';
            h += '<div class="pb-setting-group"><label>Text Color</label><input type="color" data-key="textColor" value="' + (d.textColor||'#2C3E50') + '" style="width:100%;height:36px;padding:2px"></div>';
            h += '<div class="pb-setting-group"><label>Padding (px)</label><input type="range" data-key="padding" min="10" max="100" value="' + (d.padding||40) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'"><span style="font-size:12px;color:#7A8B99">' + (d.padding||40) + 'px</span></div>';
            break;
    }
    h += '<button class="pb-delete-block" data-action="delete-block"><i class="fa-solid fa-trash"></i> Delete Block</button>';
    return h;
}

function settingSelect(label, key, current, opts) {
    var h = '<div class="pb-setting-group"><label>' + label + '</label><select data-key="' + key + '">';
    opts.forEach(function(o) { h += '<option value="' + o.v + '"' + (String(current)==String(o.v)?' selected':'') + '>' + o.l + '</option>'; });
    return h + '</select></div>';
}

function settingAlign(current) {
    return '<div class="pb-setting-group"><label>Alignment</label><div class="pb-align-btns">' +
        '<button data-align="left"' + (current==='left'?' class="active"':'') + '><i class="fa-solid fa-align-left"></i></button>' +
        '<button data-align="center"' + (current==='center'?' class="active"':'') + '><i class="fa-solid fa-align-center"></i></button>' +
        '<button data-align="right"' + (current==='right'?' class="active"':'') + '><i class="fa-solid fa-align-right"></i></button>' +
        '</div></div>';
}

function bindSettings(panel, block) {
    panel.querySelectorAll('[data-key]').forEach(function(inp) {
        inp.addEventListener('input', function() {
            var val = this.value;
            if (this.type === 'range') val = parseInt(val);
            if (this.tagName === 'SELECT' && !isNaN(val) && this.dataset.key !== 'style' && this.dataset.key !== 'width') val = parseInt(val);
            block.data[this.dataset.key] = val;
            refreshBlock(block);
        });
    });
    panel.querySelectorAll('[data-align]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            panel.querySelectorAll('[data-align]').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            block.data.align = this.dataset.align;
            refreshBlock(block);
        });
    });
    var uploadBtn = panel.querySelector('[data-action="upload-img"]');
    if (uploadBtn) uploadBtn.addEventListener('click', function() { triggerUpload(block.id); });
    var delBtn = panel.querySelector('[data-action="delete-block"]');
    if (delBtn) delBtn.addEventListener('click', function() { deleteBlock(block.id); });
}

function refreshBlock(block) {
    var el = document.querySelector('[data-block-id="' + block.id + '"]');
    if (!el) return;
    var body = el.querySelector('.pb-block-body');
    body.innerHTML = renderBlockContent(block);
    setupEditable(el, block);
}

// ── Image upload ──
var uploadTargetBlockId = null;
function triggerUpload(blockId) {
    uploadTargetBlockId = blockId;
    document.getElementById('fileInput').click();
}

document.getElementById('fileInput').addEventListener('change', function() {
    if (!this.files[0] || !uploadTargetBlockId) return;
    var file = this.files[0];
    var blockId = uploadTargetBlockId;
    var fd = new FormData();
    fd.append('file', file);
    fd.append('_csrf', CSRF);
    fd.append('folder', 'pages');

    toast('Uploading...');
    fetch('/admin/api/upload-file.php', { method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
        if (j.ok) {
            var i = findIndex(blockId);
            if (i >= 0) {
                blocks[i].data.src = j.url || ('/' + j.path);
                refreshBlock(blocks[i]);
                if (selectedId === blockId) showSettings(blockId);
            }
            toast('Image uploaded');
        } else {
            toast(j.error || 'Upload failed', true);
        }
    })
    .catch(function() { toast('Upload error', true); });
    this.value = '';
});

// ── Formatting toolbar ──
var fmtBar = document.getElementById('fmtBar');
function checkFormatBar() {
    var sel = window.getSelection();
    if (!sel || sel.isCollapsed || !sel.rangeCount) { fmtBar.classList.remove('visible'); return; }
    var range = sel.getRangeAt(0);
    var rect = range.getBoundingClientRect();
    if (rect.width < 2) { fmtBar.classList.remove('visible'); return; }
    fmtBar.style.top = (rect.top - 42) + 'px';
    fmtBar.style.left = Math.max(8, rect.left + rect.width/2 - 100) + 'px';
    fmtBar.classList.add('visible');
}

document.addEventListener('mousedown', function(e) {
    if (!e.target.closest('.pb-fmt')) {
        setTimeout(function() { if (window.getSelection().isCollapsed) fmtBar.classList.remove('visible'); }, 100);
    }
});

fmtBar.querySelectorAll('[data-cmd]').forEach(function(btn) {
    btn.addEventListener('mousedown', function(e) { e.preventDefault(); });
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var cmd = this.dataset.cmd;
        if (cmd === 'createLink') {
            var url = prompt('Enter URL:', 'https://');
            if (url) document.execCommand('createLink', false, url);
        } else {
            document.execCommand(cmd, false, null);
        }
        setTimeout(checkFormatBar, 10);
    });
});

// ── Sortable.js ──
function initSortable() {
    var el = document.getElementById('canvasInner');
    if (el._sortable) el._sortable.destroy();
    el._sortable = new Sortable(el, {
        handle: '.pb-block-handle .label',
        animation: 150,
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        draggable: '.pb-block',
        onEnd: function(evt) {
            if (evt.oldIndex === evt.newIndex) return;
            var moved = blocks.splice(evt.oldIndex, 1)[0];
            blocks.splice(evt.newIndex, 0, moved);
            renderAll();
        }
    });
}

// ── Palette drag → canvas ──
new Sortable(document.getElementById('blockPalette'), {
    group: { name: 'blocks', pull: 'clone', put: false },
    sort: false,
    animation: 150
});

// Palette click → add block
document.getElementById('blockPalette').addEventListener('click', function(e) {
    var item = e.target.closest('.pb-palette-item');
    if (!item) return;
    var type = item.dataset.type;
    if (pendingInsertIndex !== null) {
        addBlock(type, pendingInsertIndex);
        pendingInsertIndex = null;
    } else {
        addBlock(type);
    }
});

// ── Video URL parser ──
function parseVideoUrl(url) {
    if (!url) return '';
    var m;
    m = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]+)/);
    if (m) return 'https://www.youtube.com/embed/' + m[1];
    m = url.match(/vimeo\.com\/(\d+)/);
    if (m) return 'https://player.vimeo.com/video/' + m[1];
    return url;
}

// ── Generate clean HTML ──
function generateHTML() {
    var parts = [];
    blocks.forEach(function(b) {
        parts.push(blockToHTML(b));
    });
    return parts.join('\n');
}

function blockToHTML(b) {
    var d = b.data;
    switch(b.type) {
        case 'heading':
            var tag = 'h' + (d.level||2);
            var style = d.align && d.align !== 'left' ? ' style="text-align:'+d.align+'"' : '';
            return '<' + tag + style + '>' + esc(d.text) + '</' + tag + '>';
        case 'text':
            var style = d.align && d.align !== 'left' ? ' style="text-align:'+d.align+'"' : '';
            return '<div' + style + '>' + (d.html||'') + '</div>';
        case 'image':
            if (!d.src) return '';
            var imgStyle = 'max-width:' + (d.width||100) + '%;height:auto';
            var wrapStyle = d.align ? 'text-align:' + d.align : '';
            return '<figure' + (wrapStyle?' style="'+wrapStyle+'"':'') + '><img src="' + esc(d.src) + '" alt="' + esc(d.alt||'') + '" style="' + imgStyle + '"></figure>';
        case 'button':
            var cls = d.style === 'outline' ? 'btn-default btn-border' : 'btn-default';
            var wrapStyle = d.align ? 'text-align:' + d.align : '';
            return '<div' + (wrapStyle?' style="'+wrapStyle+'"':'') + '><a class="' + cls + '" href="' + esc(d.url||'#') + '">' + esc(d.text) + '</a></div>';
        case 'columns':
            var n = d.count || 2;
            var colClass = n === 2 ? 'col-md-6' : (n === 3 ? 'col-md-4' : 'col-md-3');
            var html = '<div class="row">';
            for (var i = 0; i < n; i++) {
                html += '<div class="' + colClass + '">' + (d.cols[i]||'') + '</div>';
            }
            return html + '</div>';
        case 'spacer':
            return '<div style="height:' + (d.height||40) + 'px"></div>';
        case 'divider':
            return '<hr style="border:0;border-top:1px ' + (d.style||'solid') + ' ' + (d.color||'#E1E7EE') + '">';
        case 'quote':
            return '<blockquote style="border-left:3px solid #1ABC9C;padding:12px 20px;font-style:italic"><p>' + esc(d.text) + '</p>' + (d.author ? '<footer>— ' + esc(d.author) + '</footer>' : '') + '</blockquote>';
        case 'list':
            return d.html || '';
        case 'video':
            if (!d.url) return '';
            var embedUrl = parseVideoUrl(d.url);
            return '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden"><iframe src="' + esc(embedUrl) + '" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" allowfullscreen></iframe></div>';
        case 'html':
            return d.code || '';
        case 'section':
            return '<section style="background:' + (d.bgColor||'#F7F9FC') + ';color:' + (d.textColor||'#2C3E50') + ';padding:' + (d.padding||40) + 'px 30px">' + (d.html||'') + '</section>';
        default:
            return '';
    }
}

// ── Save ──
document.getElementById('pb_save').addEventListener('click', function() {
    syncAllEditables();
    var title = document.getElementById('pb_title').value.trim();
    if (!title) { toast('Title required', true); return; }
    var slug = document.getElementById('pb_slug').value.trim();
    if (!slug) slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    var fd = new FormData();
    fd.append('_csrf', CSRF);
    fd.append('title', title);
    fd.append('slug', slug);
    fd.append('locale', document.getElementById('pb_locale').value);
    fd.append('status', document.getElementById('pb_status').value);
    fd.append('html', generateHTML());
    fd.append('blocks_json', JSON.stringify(blocks));

    var url = PAGE_ID ? '/admin/page-builder.php?id=' + PAGE_ID : '/admin/page-builder.php';
    fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
        if (j.ok) {
            toast('Saved!');
            if (j.redirect) setTimeout(function(){ location.href = j.redirect; }, 600);
        } else toast(j.error || 'Save failed', true);
    })
    .catch(function() { toast('Network error', true); });
});

function syncAllEditables() {
    blocks.forEach(function(block) {
        var el = document.querySelector('[data-block-id="' + block.id + '"]');
        if (el) syncEditable(block, el);
    });
}

// ── Preview ──
document.getElementById('pb_preview').addEventListener('click', function() {
    syncAllEditables();
    var html = generateHTML();
    var w = window.open('', '_blank');
    w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Preview</title>' +
        '<link href="/css/bootstrap.min.css" rel="stylesheet">' +
        '<link href="/css/custom.css" rel="stylesheet">' +
        '<link href="/css/mori.css" rel="stylesheet">' +
        '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">' +
        '<style>body{padding:40px 20px;font-family:Inter,sans-serif}.container{max-width:900px;margin:0 auto}</style>' +
        '</head><body><div class="container">' + html + '</div></body></html>');
    w.document.close();
});

// ── Keyboard shortcuts ──
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('pb_save').click();
    }
    if (e.key === 'Delete' && selectedId && !e.target.closest('[contenteditable]') && !e.target.closest('input') && !e.target.closest('textarea')) {
        deleteBlock(selectedId);
    }
    if (e.key === 'Escape') {
        selectedId = null;
        document.querySelectorAll('.pb-block.selected').forEach(function(el) { el.classList.remove('selected'); });
        showPalette();
        fmtBar.classList.remove('visible');
    }
});

// ── Utils ──
function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function toast(msg, isErr) {
    var el = document.getElementById('pbToast');
    el.textContent = msg; el.className = 'pb-toast show' + (isErr ? ' err' : '');
    setTimeout(function() { el.className = 'pb-toast'; }, 2200);
}

// ── Init ──
initBlocks();

})();
</script>
</body>
</html>
