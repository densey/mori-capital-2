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
use function Mori\setting;

Auth::requireLogin();
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
            ['k' => $key, 'v' => (string)$value]
        );
        $changed++;
    }
    AuditLog::log(Auth::userId(), 'homepage_updated', 'settings', null, "$changed fields");
    flash('ok', "Homepage content updated ($changed fields).");
    redirect(asset('admin/homepage.php'));
}

function hp(string $key, string $default = ''): string {
    return htmlspecialchars(setting($key, $default) ?? $default, ENT_QUOTES, 'UTF-8');
}

$adminPage = ['title' => 'Homepage Content', 'crumb' => 'Manage all homepage sections — text, stats, images, quotes'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>

<form method="post" class="a-form">
    <?= Csrf::field() ?>

    <!-- About Section -->
    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2><i class="fa-solid fa-building-columns"></i> About Section</h2></div>
        <div class="a-card__body">
            <label>Eyebrow label</label>
            <input type="text" name="settings[hp_about_eyebrow]" value="<?= hp('hp_about_eyebrow', 'About Mori Capital') ?>">
            <label>Description (EN)</label>
            <textarea name="settings[hp_about_text_en]" rows="3"><?= hp('hp_about_text_en', 'Founded in 1998 and headquartered in Malta, Mori Capital Management is a dedicated investor in Emerging European, Middle Eastern and African equity markets. We combine bottom-up stock picking with rigorous in-house research and active dialogue with company management.') ?></textarea>
            <label>Description (DE)</label>
            <textarea name="settings[hp_about_text_de]" rows="3"><?= hp('hp_about_text_de', '') ?></textarea>
            <label>Quote</label>
            <textarea name="settings[hp_about_quote]" rows="2"><?= hp('hp_about_quote', 'In the EEMEA region, knowledge isn\'t found in screens — it\'s earned by walking the extra mile.') ?></textarea>
            <div class="row" style="margin-top:12px;">
                <div><label>About image path</label><input type="text" name="settings[hp_about_image]" value="<?= hp('hp_about_image', 'assets/images/service/h6-service-1.webp') ?>"></div>
                <div><label>About body image path</label><input type="text" name="settings[hp_about_body_image]" value="<?= hp('hp_about_body_image', 'assets/images/about/about-hd.jpg') ?>"></div>
            </div>
        </div>
    </div>

    <!-- Stats / Counters -->
    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2><i class="fa-solid fa-chart-bar"></i> Statistics & Counters</h2></div>
        <div class="a-card__body">
            <p style="font-size:12px;color:var(--a-muted);margin-bottom:14px;">These numbers appear in multiple sections across the homepage (about, cinematic). Change once, updates everywhere.</p>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
                <div>
                    <label>Years of expertise</label>
                    <input type="number" name="settings[stat_years]" value="<?= hp('stat_years', '25') ?>">
                    <small style="color:var(--a-muted);">e.g. 25</small>
                </div>
                <div>
                    <label>Securities under coverage</label>
                    <input type="number" name="settings[stat_securities]" value="<?= hp('stat_securities', '200') ?>">
                    <small style="color:var(--a-muted);">e.g. 200</small>
                </div>
                <div>
                    <label>EEMEA markets</label>
                    <input type="number" name="settings[stat_markets]" value="<?= hp('stat_markets', '15') ?>">
                    <small style="color:var(--a-muted);">e.g. 15</small>
                </div>
                <div>
                    <label>Collective team experience (years)</label>
                    <input type="number" name="settings[stat_team_experience]" value="<?= hp('stat_team_experience', '80') ?>">
                    <small style="color:var(--a-muted);">e.g. 80</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Funds Section -->
    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2><i class="fa-solid fa-chart-pie"></i> Funds Section</h2></div>
        <div class="a-card__body">
            <label>Section description (EN)</label>
            <textarea name="settings[hp_funds_desc_en]" rows="2"><?= hp('hp_funds_desc_en', 'Our flagship vehicles offer differentiated access to distinct EEMEA market opportunities — both managed with the same disciplined, research-led approach.') ?></textarea>
            <label>Section description (DE)</label>
            <textarea name="settings[hp_funds_desc_de]" rows="2"><?= hp('hp_funds_desc_de', '') ?></textarea>
            <label>Footer note under fund cards</label>
            <input type="text" name="settings[hp_funds_footer_note]" value="<?= hp('hp_funds_footer_note', 'Managed by portfolio managers with 20+ years of EEMEA experience.') ?>">
        </div>
    </div>

    <!-- Investment Style Section -->
    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2><i class="fa-solid fa-compass"></i> Investment Style Section</h2></div>
        <div class="a-card__body">
            <label>Description (EN)</label>
            <textarea name="settings[hp_style_desc_en]" rows="2"><?= hp('hp_style_desc_en', 'Our investment philosophy is built on bottom-up stock picking with a macro overlay, in-house proprietary research, and active dialogue with company management.') ?></textarea>
            <label>Description (DE)</label>
            <textarea name="settings[hp_style_desc_de]" rows="2"><?= hp('hp_style_desc_de', '') ?></textarea>
            <label>Bullet point 1</label>
            <input type="text" name="settings[hp_style_bullet_1]" value="<?= hp('hp_style_bullet_1', 'Bottom-up stock picking with a top-down macro overlay across EEMEA markets') ?>">
            <label>Bullet point 2</label>
            <input type="text" name="settings[hp_style_bullet_2]" value="<?= hp('hp_style_bullet_2', 'Active dialogue with company management and on-the-ground research visits') ?>">
            <label>Feature item 1</label>
            <input type="text" name="settings[hp_style_feature_1]" value="<?= hp('hp_style_feature_1', 'In-house proprietary research') ?>">
            <label>Feature item 2</label>
            <input type="text" name="settings[hp_style_feature_2]" value="<?= hp('hp_style_feature_2', 'Walking the extra mile — 200+ securities') ?>">
            <label>Feature item 3</label>
            <input type="text" name="settings[hp_style_feature_3]" value="<?= hp('hp_style_feature_3', 'Disciplined risk management') ?>">
            <label>Quote</label>
            <textarea name="settings[hp_style_quote]" rows="2"><?= hp('hp_style_quote', 'We don\'t just analyse companies — we visit them, walk their factories, and meet their stakeholders.') ?></textarea>
            <label>Style section image</label>
            <input type="text" name="settings[hp_style_image]" value="<?= hp('hp_style_image', 'assets/images/hero/hero-corporate-2.jpg') ?>">
        </div>
    </div>

    <!-- Cinematic Section -->
    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2><i class="fa-solid fa-film"></i> Cinematic Section</h2></div>
        <div class="a-card__body">
            <label>Eyebrow</label>
            <input type="text" name="settings[hp_cine_eyebrow]" value="<?= hp('hp_cine_eyebrow', 'EEMEA in motion') ?>">
            <label>Title (HTML allowed for line breaks)</label>
            <input type="text" name="settings[hp_cine_title]" value="<?= hp('hp_cine_title', 'Disciplined investing, powered by data.') ?>">
            <label>Description</label>
            <textarea name="settings[hp_cine_desc]" rows="2"><?= hp('hp_cine_desc', 'Real-time portfolio analytics, on-the-ground research and active risk management — all converging into a single conviction-led process across Emerging European, Middle Eastern and African markets.') ?></textarea>
            <div class="row" style="margin-top:12px;">
                <div><label>Showcase fund name</label><input type="text" name="settings[hp_cine_fund_name]" value="<?= hp('hp_cine_fund_name', 'Mori Ottoman Fund') ?>"></div>
                <div><label>Showcase NAV display</label><input type="text" name="settings[hp_cine_nav_display]" value="<?= hp('hp_cine_nav_display', '€ 142.86') ?>"></div>
                <div><label>Showcase YTD %</label><input type="text" name="settings[hp_cine_ytd]" value="<?= hp('hp_cine_ytd', '▲ 18.4% YTD') ?>"></div>
                <div><label>10Y annualised display</label><input type="text" name="settings[hp_cine_10y]" value="<?= hp('hp_cine_10y', '+11.7%') ?>"></div>
            </div>

            <p style="font-size:12px;color:var(--a-muted);margin:18px 0 10px;font-weight:600;">Floating info chips around the showcase card</p>
            <div class="row">
                <div><label>Currency chip label</label><input type="text" name="settings[hp_cine_ccy_lbl]" value="<?= hp('hp_cine_ccy_lbl', 'Multi-currency') ?>"></div>
                <div><label>Currency chip value</label><input type="text" name="settings[hp_cine_ccy_val]" value="<?= hp('hp_cine_ccy_val', 'EUR · USD · GBP') ?>"><small style="font-size:11px;color:var(--a-muted);">Separate currencies with " · "</small></div>
            </div>
            <div class="row">
                <div><label>10Y chip label</label><input type="text" name="settings[hp_cine_10y_lbl]" value="<?= hp('hp_cine_10y_lbl', '10Y Annualised') ?>"></div>
                <div><label>10Y chip suffix</label><input type="text" name="settings[hp_cine_10y_suffix]" value="<?= hp('hp_cine_10y_suffix', 'vs benchmark') ?>"></div>
            </div>
            <div class="row">
                <div><label>Regulatory chip label</label><input type="text" name="settings[hp_cine_aum_lbl]" value="<?= hp('hp_cine_aum_lbl', 'UCITS · Daily liquidity') ?>"></div>
                <div><label>Regulatory chip value</label><input type="text" name="settings[hp_cine_aum_val]" value="<?= hp('hp_cine_aum_val', 'MFSA C66999') ?>"></div>
            </div>
        </div>
    </div>

    <!-- Investment Principles (Investment Style page) -->
    <div class="a-card" style="margin-bottom:22px;">
        <div class="a-card__head"><h2><i class="fa-solid fa-compass"></i> Investment Principles (Investment Style page)</h2></div>
        <div class="a-card__body">
            <p style="font-size:12px;color:var(--a-muted);margin-bottom:14px;">These 5 principles appear on the Investment Style page as cards. Leave title empty to use the hardcoded default. Icon: Font Awesome class name (e.g. <code>fa-magnifying-glass-chart</code>).</p>
            <?php for ($pi = 1; $pi <= 5; $pi++): ?>
            <div style="background:var(--a-border-soft);padding:14px 18px;border-radius:8px;margin-bottom:12px;">
                <div style="font-weight:700;font-size:12px;color:var(--a-navy);margin-bottom:8px;">Principle <?= $pi ?></div>
                <div style="display:grid;grid-template-columns:140px 1fr;gap:10px;">
                    <div><label style="font-size:11px;">Icon class</label><input type="text" name="settings[principle_<?= $pi ?>_icon]" value="<?= hp("principle_{$pi}_icon") ?>" placeholder="fa-magnifying-glass-chart"></div>
                    <div><label style="font-size:11px;">Title</label><input type="text" name="settings[principle_<?= $pi ?>_title]" value="<?= hp("principle_{$pi}_title") ?>"></div>
                </div>
                <div style="margin-top:8px;"><label style="font-size:11px;">Description</label><textarea name="settings[principle_<?= $pi ?>_desc]" rows="2" style="font-size:12px;"><?= hp("principle_{$pi}_desc") ?></textarea></div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <button type="submit" class="a-btn" style="margin-bottom:30px;"><i class="fa-solid fa-check"></i> Save All Homepage Content</button>
</form>

<?php include __DIR__ . '/partials/footer.php'; ?>
