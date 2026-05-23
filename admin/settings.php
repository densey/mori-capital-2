<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use Mori\AuditLog;
use function Mori\e;
use function Mori\asset;
use function Mori\flash;
use function Mori\redirect;

Auth::requireRole('super_admin');
$db = Database::instance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $changed = 0;
    foreach ($_POST['settings'] ?? [] as $key => $value) {
        $key = preg_replace('/[^a-z0-9_]/', '', strtolower($key));
        if (!$key) continue;
        $db->query(
            'INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
            ['k' => $key, 'v' => is_array($value) ? implode(',', $value) : (string)$value]
        );
        $changed++;
    }
    AuditLog::log(Auth::userId(), 'settings_updated', 'settings', null, "$changed settings");
    flash('ok', "$changed settings updated.");
    redirect(asset('admin/settings.php'));
}

$settings = [];
foreach ($db->fetchAll('SELECT setting_key, setting_value, setting_group FROM settings') as $row) {
    $settings[$row['setting_key']] = $row;
}

function s(array $settings, string $key): string {
    return htmlspecialchars($settings[$key]['setting_value'] ?? '', ENT_QUOTES, 'UTF-8');
}

$adminPage = ['title' => 'Settings', 'crumb' => 'Site title, contact details, SEO, security and uploads'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>

<form method="post" class="a-form">
    <?= Csrf::field() ?>

    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2>General</h2></div>
        <div class="a-card__body">
            <div class="row">
                <div><label>Site title</label><input type="text" name="settings[site_title]" value="<?= s($settings,'site_title') ?>"></div>
                <div><label>Site tagline</label><input type="text" name="settings[site_tagline]" value="<?= s($settings,'site_tagline') ?>"></div>
                <div><label>Default language</label><select name="settings[default_locale]"><option value="en" <?= s($settings,'default_locale')==='en'?'selected':'' ?>>English</option><option value="de" <?= s($settings,'default_locale')==='de'?'selected':'' ?>>Deutsch</option></select></div>
            </div>
        </div>
    </div>

    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2>Contact</h2></div>
        <div class="a-card__body">
            <div class="row">
                <div><label>Email</label><input type="email" name="settings[contact_email]" value="<?= s($settings,'contact_email') ?>"></div>
                <div><label>Phone</label><input type="text" name="settings[contact_phone]" value="<?= s($settings,'contact_phone') ?>"></div>
            </div>
            <label>Address (multi-line)</label>
            <textarea name="settings[contact_address]" rows="3"><?= s($settings,'contact_address') ?></textarea>
        </div>
    </div>

    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2>Compliance</h2></div>
        <div class="a-card__body">
            <div class="row">
                <div><label>Regulator</label><input type="text" name="settings[mfsa_authority]" value="<?= s($settings,'mfsa_authority') ?>"></div>
                <div><label>License / firm reference</label><input type="text" name="settings[mfsa_license]" value="<?= s($settings,'mfsa_license') ?>"></div>
            </div>
        </div>
    </div>

    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2>SEO</h2></div>
        <div class="a-card__body">
            <label>Default meta title</label>
            <input type="text" name="settings[seo_default_title]" value="<?= s($settings,'seo_default_title') ?>">
            <label>Default meta description</label>
            <textarea name="settings[seo_default_desc]" rows="2"><?= s($settings,'seo_default_desc') ?></textarea>
            <label>Google Analytics ID (e.g. <code>G-XXXXXXXXXX</code>)</label>
            <input type="text" name="settings[google_analytics_id]" value="<?= s($settings,'google_analytics_id') ?>" placeholder="G-XXXXXXXXXX">
            <div class="hint">Loaded asynchronously in the &lt;head&gt; on every page. Leave blank to disable.</div>
            <label>Default Open Graph image (full URL or path under <code>assets/</code>)</label>
            <input type="text" name="settings[og_default_image]" value="<?= s($settings,'og_default_image') ?>" placeholder="assets/images/og-cover.jpg">
            <div class="hint">Shown when the site is shared on Facebook, LinkedIn, WhatsApp, X. Recommended 1200×630px.</div>
        </div>
    </div>

    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head">
            <h2><i class="fa-solid fa-code"></i> Custom Code (&lt;head&gt; and footer)</h2>
        </div>
        <div class="a-card__body">
            <div class="a-alert warn" style="margin-bottom:18px;">
                <strong><i class="fa-solid fa-triangle-exclamation"></i> Powerful — and risky.</strong>
                Everything you paste below is rendered raw into every page (no escaping). Use it for trusted
                snippets only: Google Tag Manager, Meta Pixel, custom meta tags, schema.org JSON-LD, live-chat
                widgets, etc. Don't paste anything from an untrusted source.
            </div>

            <label><i class="fa-solid fa-arrow-up"></i> Custom &lt;head&gt; code</label>
            <textarea name="settings[custom_head_code]" rows="9" style="font-family:'JetBrains Mono','Menlo',Consolas,monospace;font-size:12.5px;background:#0E1F36;color:#9EC9E2;border-color:#0E1F36;" placeholder="<!-- e.g. Google Tag Manager -->&#10;<script>(function(w,d,s,l,i){...})(window,document,'script','dataLayer','GTM-XXXXXX');</script>&#10;&#10;<!-- e.g. Meta Pixel -->&#10;<script>!function(f,b,e,v,n,t,s){...}</script>&#10;&#10;<!-- e.g. custom meta -->&#10;<meta name='robots' content='index, follow'>"><?= s($settings,'custom_head_code') ?></textarea>
            <div class="hint">Loaded inside &lt;head&gt;, AFTER theme stylesheets and Google Analytics. Ideal for: GTM, Meta Pixel, LinkedIn Insight Tag, Hotjar, structured-data JSON-LD, custom meta tags.</div>

            <label style="margin-top:22px;"><i class="fa-solid fa-arrow-down"></i> Custom footer code (before &lt;/body&gt;)</label>
            <textarea name="settings[custom_footer_code]" rows="9" style="font-family:'JetBrains Mono','Menlo',Consolas,monospace;font-size:12.5px;background:#0E1F36;color:#9EC9E2;border-color:#0E1F36;" placeholder="<!-- e.g. GTM noscript fallback -->&#10;<noscript><iframe src='https://www.googletagmanager.com/ns.html?id=GTM-XXXXXX' ...></iframe></noscript>&#10;&#10;<!-- e.g. Tawk.to live chat -->&#10;<script type='text/javascript'>var Tawk_API=Tawk_API||{}; ...</script>&#10;&#10;<!-- e.g. delayed-load Crisp / Intercom -->&#10;<script>setTimeout(function(){ ... }, 3000);</script>"><?= s($settings,'custom_footer_code') ?></textarea>
            <div class="hint">Loaded just before &lt;/body&gt;, AFTER all theme JS. Best for: GTM noscript fallback, live-chat widgets, third-party tracking pixels that should not block page load.</div>
        </div>
    </div>

    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2>Social</h2></div>
        <div class="a-card__body">
            <label>LinkedIn URL</label>
            <input type="url" name="settings[linkedin_url]" value="<?= s($settings,'linkedin_url') ?>">
        </div>
    </div>

    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2>Security &amp; Uploads</h2></div>
        <div class="a-card__body">
            <div class="row">
                <div><label>Session timeout (minutes)</label><input type="number" name="settings[session_timeout_min]" value="<?= s($settings,'session_timeout_min') ?>"></div>
                <div><label>Password min length</label><input type="number" name="settings[password_min_length]" value="<?= s($settings,'password_min_length') ?>"></div>
                <div><label>Upload max (MB)</label><input type="number" name="settings[upload_max_mb]" value="<?= s($settings,'upload_max_mb') ?>"></div>
            </div>
        </div>
    </div>

    <button class="a-btn lg" type="submit"><i class="fa-solid fa-save"></i> Save settings</button>
</form>

<?php include __DIR__ . '/partials/footer.php'; ?>
