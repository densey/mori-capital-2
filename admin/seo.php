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

Auth::requireLogin();

$keys = [
    'seo_default_title'   => '',
    'seo_default_desc'    => '',
    'google_analytics_id' => '',
    'og_default_image'    => '',
    'custom_head_code'    => '',
    'custom_footer_code'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $db = Database::instance();
    $changed = 0;
    foreach ($keys as $k => $_) {
        $val = $_POST['settings'][$k] ?? '';
        $db->query(
            'INSERT INTO settings (setting_key, setting_value, setting_group, updated_at)
                  VALUES (:k, :v, :g, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
            ['k' => $k, 'v' => $val, 'g' => 'seo']
        );
        $changed++;
    }
    AuditLog::log(Auth::userId(), 'seo_updated', 'settings', null, "$changed keys");
    flash('ok', 'SEO + custom code saved.');
    redirect(asset('admin/seo.php'));
}

// Load current values
$rows = Database::instance()->fetchAll(
    'SELECT setting_key, setting_value FROM settings WHERE setting_key IN (' .
    implode(',', array_map(fn($k) => "'$k'", array_keys($keys))) . ')'
);
foreach ($rows as $r) {
    if (array_key_exists($r['setting_key'], $keys)) $keys[$r['setting_key']] = $r['setting_value'];
}

$adminPage = ['title' => 'SEO & Custom Code', 'crumb' => 'Search engine optimisation, social previews, analytics and custom HTML/JS'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><i class="fa-solid fa-circle-check"></i> <?= e($ok) ?></div><?php endif; ?>

<form method="post" class="a-form">
    <?= Csrf::field() ?>

    <!-- Search engine meta -->
    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2><i class="fa-solid fa-magnifying-glass"></i> Search engine meta</h2></div>
        <div class="a-card__body">
            <label>Default page title (fallback when a page has no meta_title)</label>
            <input type="text" name="settings[seo_default_title]" value="<?= e($keys['seo_default_title']) ?>" maxlength="160" placeholder="Mori Capital Management — Specialists in EEMEA Markets">
            <div class="hint">Used in browser tab and Google search results. Aim for 50–60 characters.</div>

            <label>Default page description (fallback)</label>
            <textarea name="settings[seo_default_desc]" rows="3" maxlength="320" placeholder="Mori Capital Management Ltd. — independent EEMEA specialist asset manager. Home of the Mori Eastern European Fund and Mori Ottoman Fund."><?= e($keys['seo_default_desc']) ?></textarea>
            <div class="hint">Shown under the title in search results. Aim for 150–160 characters.</div>
        </div>
    </div>

    <!-- Social sharing -->
    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2><i class="fa-solid fa-share-nodes"></i> Social preview (Open Graph)</h2></div>
        <div class="a-card__body">
            <label>Default OG image (full URL or path under <code>assets/</code>)</label>
            <input type="text" name="settings[og_default_image]" value="<?= e($keys['og_default_image']) ?>" placeholder="assets/images/og-cover.jpg">
            <div class="hint">Shown when the site is shared on Facebook, LinkedIn, WhatsApp or X. Recommended size: <strong>1200×630px</strong>. PNG/JPG/WEBP.</div>
        </div>
    </div>

    <!-- Analytics ID -->
    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2><i class="fa-solid fa-chart-area"></i> Google Analytics</h2></div>
        <div class="a-card__body">
            <label>Measurement ID</label>
            <input type="text" name="settings[google_analytics_id]" value="<?= e($keys['google_analytics_id']) ?>" placeholder="G-XXXXXXXXXX">
            <div class="hint">From <a href="https://analytics.google.com/" target="_blank" rel="noopener noreferrer">Google Analytics</a> → Admin → Data Streams → Web → Measurement ID. Loaded asynchronously on every page. Leave blank to disable.</div>
        </div>
    </div>

    <!-- Custom head code -->
    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2><i class="fa-solid fa-code"></i> Custom &lt;head&gt; code</h2></div>
        <div class="a-card__body">
            <div class="a-alert warn" style="margin-bottom:16px;font-size:13px;">
                <strong><i class="fa-solid fa-triangle-exclamation"></i> This is rendered raw — no escaping.</strong>
                Only paste snippets from trusted sources (Google, Meta, LinkedIn, Hotjar, Tawk.to, etc.).
            </div>
            <textarea name="settings[custom_head_code]" rows="14" style="font-family:'JetBrains Mono','Menlo',Consolas,monospace;font-size:12.5px;background:#0E1F36;color:#9EC9E2;border-color:#0E1F36;" placeholder="<!-- Google Tag Manager -->&#10;<script>(function(w,d,s,l,i){w[l]=w[l]||[];...})(window,document,'script','dataLayer','GTM-XXXXXX');</script>&#10;&#10;<!-- Meta Pixel -->&#10;<script>!function(f,b,e,v,n,t,s){...}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js'); fbq('init','123456789'); fbq('track','PageView');</script>&#10;&#10;<!-- LinkedIn Insight Tag -->&#10;<script>_linkedin_partner_id = '12345';...</script>&#10;&#10;<!-- Schema.org JSON-LD -->&#10;<script type='application/ld+json'>{ '@context':'https://schema.org', '@type':'Organization', ... }</script>"><?= e($keys['custom_head_code']) ?></textarea>
            <div class="hint">
                Loaded inside <code>&lt;head&gt;</code>, after theme stylesheets and Google Analytics.
                Best for: <strong>GTM</strong>, Meta Pixel, LinkedIn Insight Tag, Hotjar, Microsoft Clarity, custom meta tags, schema.org JSON-LD, Search Console verification tags.
            </div>
        </div>
    </div>

    <!-- Custom footer code -->
    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2><i class="fa-solid fa-code"></i> Custom footer code <span class="a-badge muted">before &lt;/body&gt;</span></h2></div>
        <div class="a-card__body">
            <textarea name="settings[custom_footer_code]" rows="14" style="font-family:'JetBrains Mono','Menlo',Consolas,monospace;font-size:12.5px;background:#0E1F36;color:#9EC9E2;border-color:#0E1F36;" placeholder="<!-- GTM noscript fallback -->&#10;<noscript><iframe src='https://www.googletagmanager.com/ns.html?id=GTM-XXXXXX' height='0' width='0' style='display:none'></iframe></noscript>&#10;&#10;<!-- Tawk.to live chat -->&#10;<script type='text/javascript'>var Tawk_API=Tawk_API||{},Tawk_LoadStart=new Date(); (function(){var s1=document.createElement('script'),...})();</script>&#10;&#10;<!-- Delay-load Crisp/Intercom -->&#10;<script>setTimeout(function(){ ...load chat... }, 3000);</script>"><?= e($keys['custom_footer_code']) ?></textarea>
            <div class="hint">
                Loaded just before <code>&lt;/body&gt;</code>, after all theme JS.
                Best for: GTM <code>&lt;noscript&gt;</code> fallback, <strong>live-chat widgets</strong> (Tawk.to, Crisp, Intercom, HubSpot), delayed-load tracking pixels.
            </div>
        </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:22px;">
        <button class="a-btn lg" type="submit"><i class="fa-solid fa-save"></i> Save SEO &amp; custom code</button>
        <a class="a-btn ghost lg" href="<?= asset('/') ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up-right-from-square"></i> View site</a>
    </div>
</form>

<?php include __DIR__ . '/partials/footer.php'; ?>
