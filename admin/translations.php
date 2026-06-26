<?php
/**
 * Translation Center — one place to manage every German content field.
 *
 * Aggregates rows from funds, team_members, settings, pages, insights,
 * hero_slides and fund_announcements that have an English / DE field pair
 * or a locale='de' counterpart. The page shows EN source content alongside
 * a DE editor; saves go through AJAX (no full reload).
 */
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use Mori\AuditLog;
use function Mori\e;
use function Mori\asset;
use function Mori\flash;
use function Mori\redirect;

Auth::requireLogin();
$db = Database::instance();

// ─── AJAX: save a translation ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        if (!Csrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null)) {
            throw new \Exception('Invalid CSRF token.');
        }
        $type   = $_POST['type']   ?? '';      // funds|team|setting|page|insight|hero|announcement
        $id     = (string)($_POST['id'] ?? '');
        $field  = $_POST['field']  ?? '';
        $value  = (string)($_POST['value'] ?? '');

        switch ($type) {
            case 'fund':
                if (!in_array($field, ['name_de','description_de','objective_de'], true)) throw new \Exception('Bad field.');
                $db->update('funds', [$field => $value], ['id' => (int)$id]);
                AuditLog::log(Auth::userId(), 'translation_saved', 'funds', (int)$id, "$field");
                break;
            case 'team':
                if (!in_array($field, ['title_de','bio_de'], true)) throw new \Exception('Bad field.');
                $db->update('team_members', [$field => $value], ['id' => (int)$id]);
                AuditLog::log(Auth::userId(), 'translation_saved', 'team_members', (int)$id, "$field");
                break;
            case 'setting':
                // $id is the setting_key (e.g. hp_about_text_de)
                $key = preg_replace('/[^a-z0-9_]/', '', strtolower($id));
                if (!$key) throw new \Exception('Bad key.');
                $db->query(
                    'INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, NOW())
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
                    ['k' => $key, 'v' => $value]
                );
                AuditLog::log(Auth::userId(), 'translation_saved', 'settings', null, "$key");
                break;
            case 'page':
                // $id is the DE page id (rows are per-locale)
                if (!in_array($field, ['title','meta_description','body'], true)) throw new \Exception('Bad field.');
                $db->update('pages', [$field => $value], ['id' => (int)$id]);
                AuditLog::log(Auth::userId(), 'translation_saved', 'pages', (int)$id, "$field");
                break;
            case 'insight':
                if (!in_array($field, ['title','excerpt','body'], true)) throw new \Exception('Bad field.');
                $db->update('insights', [$field => $value], ['id' => (int)$id]);
                AuditLog::log(Auth::userId(), 'translation_saved', 'insights', (int)$id, "$field");
                break;
            case 'hero':
                if (!in_array($field, ['title_de','subtitle_de'], true)) throw new \Exception('Bad field.');
                $db->update('hero_slides', [$field => $value], ['id' => (int)$id]);
                AuditLog::log(Auth::userId(), 'translation_saved', 'hero_slides', (int)$id, "$field");
                break;
            case 'announcement':
                if (!in_array($field, ['title','body'], true)) throw new \Exception('Bad field.');
                $db->update('fund_announcements', [$field => $value], ['id' => (int)$id]);
                AuditLog::log(Auth::userId(), 'translation_saved', 'fund_announcements', (int)$id, "$field");
                break;
            default:
                throw new \Exception('Unknown type: ' . $type);
        }
        echo json_encode(['ok' => true]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─── Load all translatable rows ──────────────────────────────────────────────
$sections = [];

// 1) Funds
$funds = $db->fetchAll('SELECT id, slug, name_en, name_de, description_en, description_de, objective_en, objective_de FROM funds ORDER BY display_order');
$sections['funds'] = [
    'icon'  => 'fa-chart-pie',
    'title' => 'Funds',
    'rows'  => array_map(fn($f) => [
        'id'     => (int)$f['id'],
        'label'  => $f['slug'],
        'fields' => [
            ['field' => 'name_de',        'label' => 'Name (DE)',        'en' => $f['name_en'],        'de' => $f['name_de'],        'type' => 'text'],
            ['field' => 'description_de', 'label' => 'Description (DE)', 'en' => $f['description_en'], 'de' => $f['description_de'], 'type' => 'textarea'],
            ['field' => 'objective_de',   'label' => 'Objective (DE)',   'en' => $f['objective_en'],   'de' => $f['objective_de'],   'type' => 'textarea'],
        ],
    ], $funds),
];

// 2) Team
$team = $db->fetchAll('SELECT id, slug, name, title_en, title_de, bio_en, bio_de FROM team_members WHERE status = "active" ORDER BY display_order');
$sections['team'] = [
    'icon'  => 'fa-users',
    'title' => 'Team Members',
    'rows'  => array_map(fn($t) => [
        'id'     => (int)$t['id'],
        'label'  => $t['name'],
        'fields' => [
            ['field' => 'title_de', 'label' => 'Title (DE)', 'en' => $t['title_en'], 'de' => $t['title_de'], 'type' => 'text'],
            ['field' => 'bio_de',   'label' => 'Bio (DE)',   'en' => $t['bio_en'],   'de' => $t['bio_de'],   'type' => 'textarea'],
        ],
    ], $team),
];

// 3) Homepage settings — pair every <key> with <key>_de
$settingsRows = $db->fetchAll('SELECT setting_key, setting_value FROM settings');
$settingsMap  = [];
foreach ($settingsRows as $r) $settingsMap[$r['setting_key']] = $r['setting_value'];

$hpKeys = [
    // About section
    ['hp_about_eyebrow',    'About — Eyebrow'],
    ['hp_about_text_en',    'About — Description (paired with hp_about_text_de)', 'hp_about_text_de'],
    ['hp_about_quote',      'About — Quote'],
    // Funds section
    ['hp_funds_desc_en',    'Funds section — Description (paired with hp_funds_desc_de)', 'hp_funds_desc_de'],
    ['hp_funds_footer_note','Funds section — Footer note'],
    // Style section
    ['hp_style_desc_en',    'Style — Description (paired with hp_style_desc_de)', 'hp_style_desc_de'],
    ['hp_style_bullet_1',   'Style — Bullet 1'],
    ['hp_style_bullet_2',   'Style — Bullet 2'],
    ['hp_style_feature_1',  'Style — Feature 1'],
    ['hp_style_feature_2',  'Style — Feature 2'],
    ['hp_style_feature_3',  'Style — Feature 3'],
    ['hp_style_quote',      'Style — Quote'],
    // Cinematic section
    ['hp_cine_eyebrow',     'Cinematic — Eyebrow'],
    ['hp_cine_title',       'Cinematic — Title'],
    ['hp_cine_desc',        'Cinematic — Description'],
];
$settingRows = [];
foreach ($hpKeys as $h) {
    $enKey = $h[0]; $label = $h[1];
    $deKey = $h[2] ?? ($enKey . '_de');
    // If the EN key ends in _en, the DE pair is _de of the same stem
    if (str_ends_with($enKey, '_en')) {
        $deKey = substr($enKey, 0, -3) . '_de';
    }
    $settingRows[] = [
        'id'    => $deKey,
        'label' => $label,
        'fields' => [
            ['field' => $deKey, 'label' => 'DE value', 'en' => $settingsMap[$enKey] ?? '', 'de' => $settingsMap[$deKey] ?? '', 'type' => 'textarea'],
        ],
    ];
}
// Investment principles (5)
for ($p = 1; $p <= 5; $p++) {
    foreach (['title', 'desc'] as $f) {
        $enKey = "principle_{$p}_{$f}";
        $deKey = "principle_{$p}_{$f}_de";
        if (!empty($settingsMap[$enKey])) {
            $settingRows[] = [
                'id'    => $deKey,
                'label' => "Principle {$p} — " . ucfirst($f),
                'fields' => [
                    ['field' => $deKey, 'label' => 'DE value', 'en' => $settingsMap[$enKey] ?? '', 'de' => $settingsMap[$deKey] ?? '', 'type' => $f === 'desc' ? 'textarea' : 'text'],
                ],
            ];
        }
    }
}
$sections['settings'] = [
    'icon'  => 'fa-sliders',
    'title' => 'Homepage Copy',
    'rows'  => $settingRows,
];

// 4) Pages — pair EN with DE by slug
$enPages = $db->fetchAll('SELECT id, slug, title, meta_description, body FROM pages WHERE locale = "en"');
$dePagesMap = [];
foreach ($db->fetchAll('SELECT id, slug, title, meta_description, body FROM pages WHERE locale = "de"') as $p) {
    $dePagesMap[$p['slug']] = $p;
}
$pageRows = [];
foreach ($enPages as $p) {
    $de = $dePagesMap[$p['slug']] ?? null;
    $pageRows[] = [
        'id'    => (int)($de['id'] ?? 0),
        'label' => $p['slug'] . ($de ? '' : ' (no DE row — save will fail; create one from Pages first)'),
        'fields' => $de ? [
            ['field' => 'title',            'label' => 'Title (DE)',             'en' => $p['title'],            'de' => $de['title'],            'type' => 'text'],
            ['field' => 'meta_description', 'label' => 'Meta description (DE)',  'en' => $p['meta_description'], 'de' => $de['meta_description'], 'type' => 'textarea'],
            ['field' => 'body',             'label' => 'Body (DE, HTML)',        'en' => $p['body'],             'de' => $de['body'],             'type' => 'textarea_big'],
        ] : [],
    ];
}
$sections['pages'] = [
    'icon'  => 'fa-file-lines',
    'title' => 'Pages (DE versions)',
    'rows'  => $pageRows,
];

// 5) Insights — pair EN with DE by slug
$enInsights = $db->fetchAll('SELECT id, slug, title, excerpt, body FROM insights WHERE locale = "en" AND status = "published"');
$deInsightsMap = [];
foreach ($db->fetchAll('SELECT id, slug, title, excerpt, body FROM insights WHERE locale = "de"') as $i) {
    $deInsightsMap[$i['slug']] = $i;
}
$insightRows = [];
foreach ($enInsights as $i) {
    $de = $deInsightsMap[$i['slug']] ?? null;
    $insightRows[] = [
        'id'    => (int)($de['id'] ?? 0),
        'label' => $i['title'],
        'fields' => $de ? [
            ['field' => 'title',   'label' => 'Title (DE)',        'en' => $i['title'],   'de' => $de['title'],   'type' => 'text'],
            ['field' => 'excerpt', 'label' => 'Excerpt (DE)',      'en' => $i['excerpt'], 'de' => $de['excerpt'], 'type' => 'textarea'],
            ['field' => 'body',    'label' => 'Body (DE, HTML)',   'en' => $i['body'],    'de' => $de['body'],    'type' => 'textarea_big'],
        ] : [['note' => 'No DE row — run the German content migration or create a draft from Mori Views first.']],
    ];
}
$sections['insights'] = [
    'icon'  => 'fa-newspaper',
    'title' => 'Mori Views / Insights',
    'rows'  => $insightRows,
];

// 6) Hero slides — title + subtitle DE (column exists if hero migration ran)
try {
    $hero = $db->fetchAll('SELECT id, media_path, title, subtitle FROM hero_slides ORDER BY display_order');
    $heroRows = array_map(fn($h) => [
        'id'    => (int)$h['id'],
        'label' => basename($h['media_path'] ?: ('slide #' . $h['id'])),
        'fields' => [
            ['field' => 'title_de',    'label' => 'Title (DE) — leave blank to use EN title',    'en' => $h['title'],    'de' => '', 'type' => 'text', 'note' => 'Hero slides use a single title field; the DE override is read from setting_i18n() when present.'],
            ['field' => 'subtitle_de', 'label' => 'Subtitle (DE) — leave blank to use EN subtitle', 'en' => $h['subtitle'], 'de' => '', 'type' => 'textarea'],
        ],
    ], $hero);
    $sections['hero'] = ['icon' => 'fa-panorama', 'title' => 'Hero Slider', 'rows' => $heroRows];
} catch (\Throwable) {}

// 7) Fund announcements — these are per-locale rows; show ones tagged 'en' or 'any'
try {
    $announcements = $db->fetchAll('SELECT id, slug, locale, title, body FROM fund_announcements WHERE status = "published" ORDER BY publish_date DESC LIMIT 50');
    $annRows = array_map(fn($a) => [
        'id'    => (int)$a['id'],
        'label' => $a['title'] . '  (locale=' . $a['locale'] . ')',
        'fields' => [
            ['field' => 'title', 'label' => 'Title',         'en' => $a['title'], 'de' => '', 'type' => 'text',  'note' => 'For announcements with locale=de, edit this row directly; for locale=any duplicate it as a DE-only entry from Fund Announcements.'],
            ['field' => 'body',  'label' => 'Body (HTML)',   'en' => $a['body'],  'de' => '', 'type' => 'textarea_big'],
        ],
    ], $announcements);
    $sections['announcements'] = ['icon' => 'fa-bullhorn', 'title' => 'Fund Announcements', 'rows' => $annRows];
} catch (\Throwable) {}

// ─── Render ──────────────────────────────────────────────────────────────────
$csrfToken = Csrf::token();
$adminPage = ['title' => 'Translation Center', 'crumb' => 'Manage every German content field in one place'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>

<style>
.tc-section { margin-bottom: 28px; }
.tc-section__head { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 700; color: var(--a-navy); padding: 10px 0; border-bottom: 2px solid var(--a-border); margin-bottom: 14px; }
.tc-section__head i { color: var(--a-teal); }
.tc-row { background: #fff; border: 1px solid var(--a-border); border-radius: 8px; padding: 14px 18px; margin-bottom: 12px; }
.tc-row__label { font-weight: 700; font-size: 13px; color: var(--a-navy); margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
.tc-field { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px; }
.tc-field__col label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--a-muted); font-weight: 600; display: block; margin-bottom: 4px; }
.tc-field__en { background: var(--a-border-soft); padding: 10px 12px; border-radius: 5px; font-size: 12.5px; color: var(--a-text-soft); white-space: pre-wrap; max-height: 200px; overflow-y: auto; line-height: 1.55; }
.tc-field__de input, .tc-field__de textarea { width: 100%; padding: 9px 11px; border: 1px solid var(--a-border); border-radius: 5px; font: inherit; font-size: 13px; }
.tc-field__de textarea { resize: vertical; min-height: 88px; line-height: 1.55; }
.tc-field__de textarea.big { min-height: 240px; font-family: 'Courier New', monospace; font-size: 12px; }
.tc-field__de .save-status { font-size: 11px; color: var(--a-muted); margin-top: 4px; height: 14px; transition: color .15s; }
.tc-field__de .save-status.saved { color: var(--a-success, #27AE60); font-weight: 600; }
.tc-field__de .save-status.err { color: var(--a-danger, #E74C3C); font-weight: 600; }
.tc-empty { color: var(--a-muted); font-style: italic; padding: 8px 0; font-size: 12.5px; }
.tc-toc { background: #fff; border: 1px solid var(--a-border); border-radius: 8px; padding: 14px 18px; margin-bottom: 22px; }
.tc-toc__title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--a-muted); font-weight: 700; margin-bottom: 8px; }
.tc-toc__list { display: flex; flex-wrap: wrap; gap: 8px; }
.tc-toc__list a { padding: 6px 12px; background: var(--a-border-soft); color: var(--a-navy); border-radius: 999px; font-size: 12.5px; font-weight: 600; text-decoration: none; }
.tc-toc__list a:hover { background: var(--a-teal); color: #fff; }
</style>

<div class="tc-toc">
    <div class="tc-toc__title">Jump to section</div>
    <div class="tc-toc__list">
        <?php foreach ($sections as $key => $sec): ?>
        <a href="#sec-<?= e($key) ?>"><i class="fa-solid <?= e($sec['icon']) ?>"></i> <?= e($sec['title']) ?> <span style="opacity:.5;">(<?= count($sec['rows']) ?>)</span></a>
        <?php endforeach; ?>
    </div>
</div>

<?php foreach ($sections as $key => $sec): ?>
<div class="tc-section" id="sec-<?= e($key) ?>">
    <div class="tc-section__head"><i class="fa-solid <?= e($sec['icon']) ?>"></i> <?= e($sec['title']) ?></div>
    <?php if (empty($sec['rows'])): ?>
    <div class="tc-empty">Nothing to translate yet in this section.</div>
    <?php else: foreach ($sec['rows'] as $row):
        $type = $key === 'settings' ? 'setting' : rtrim($key, 's');
        if ($key === 'announcements') $type = 'announcement';
        if ($key === 'insights')      $type = 'insight';
        if ($key === 'hero')          $type = 'hero';
    ?>
    <div class="tc-row">
        <div class="tc-row__label"><i class="fa-regular fa-pen-to-square"></i> <?= e($row['label']) ?></div>
        <?php foreach ($row['fields'] as $f):
            if (!empty($f['note']) && empty($f['field'])): ?>
                <div class="tc-empty"><?= e($f['note']) ?></div>
            <?php continue; endif;
        ?>
        <div class="tc-field" data-type="<?= e($type) ?>" data-id="<?= e($row['id']) ?>" data-field="<?= e($f['field']) ?>">
            <div class="tc-field__col">
                <label>English source</label>
                <div class="tc-field__en"><?= e((string)$f['en']) ?: '<em>(empty)</em>' ?></div>
            </div>
            <div class="tc-field__col tc-field__de">
                <label><?= e($f['label']) ?></label>
                <?php if (($f['type'] ?? 'text') === 'text'): ?>
                    <input type="text" value="<?= e((string)$f['de']) ?>">
                <?php elseif (($f['type'] ?? '') === 'textarea_big'): ?>
                    <textarea class="big"><?= e((string)$f['de']) ?></textarea>
                <?php else: ?>
                    <textarea><?= e((string)$f['de']) ?></textarea>
                <?php endif; ?>
                <div class="save-status">Auto-saves on blur.</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; endif; ?>
</div>
<?php endforeach; ?>

<script>
(function () {
    var csrf = <?= json_encode($csrfToken) ?>;
    document.querySelectorAll('.tc-field').forEach(function (wrap) {
        var input = wrap.querySelector('input, textarea');
        var status = wrap.querySelector('.save-status');
        if (!input) return;
        var initial = input.value;
        input.addEventListener('blur', function () {
            if (input.value === initial) return;
            status.textContent = 'Saving…';
            status.className = 'save-status';
            var fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('type', wrap.dataset.type);
            fd.append('id', wrap.dataset.id);
            fd.append('field', wrap.dataset.field);
            fd.append('value', input.value);
            fetch(window.location.pathname, {
                method: 'POST',
                body: fd,
                headers: { 'X-CSRF-Token': csrf }
            })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.ok) {
                    initial = input.value;
                    status.textContent = '✓ Saved';
                    status.className = 'save-status saved';
                    setTimeout(function () { status.textContent = 'Auto-saves on blur.'; status.className = 'save-status'; }, 2500);
                } else {
                    status.textContent = 'Save failed: ' + (j.error || 'unknown');
                    status.className = 'save-status err';
                }
            })
            .catch(function () {
                status.textContent = 'Network error — try again.';
                status.className = 'save-status err';
            });
        });
    });
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
