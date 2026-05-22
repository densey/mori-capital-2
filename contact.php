<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Csrf;
use Mori\Database;
use Mori\Auth;
use function Mori\e;
use function Mori\asset;
use function Mori\setting;
use function Mori\flash;
use function Mori\t;

// Handle form post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        flash('contact_error', t('contact.form.error'));
    } else {
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $hp      = trim($_POST['website'] ?? ''); // honeypot

        if ($hp !== '') {
            // Bot — silently succeed
            flash('contact_ok', t('contact.form.thanks'));
        } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
            flash('contact_error', 'Please fill in all required fields with valid data.');
        } else {
            try {
                Database::instance()->insert('contact_messages', [
                    'name'       => mb_substr($name, 0, 120),
                    'email'      => mb_substr($email, 0, 190),
                    'subject'    => mb_substr($subject, 0, 255),
                    'message'    => mb_substr($message, 0, 5000),
                    'ip_address' => Auth::clientIp(),
                    'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                ]);
                flash('contact_ok', t('contact.form.thanks'));
            } catch (\Throwable $e) {
                flash('contact_error', t('contact.form.error'));
            }
        }
        header('Location: ' . asset('contact.php#contact-form'));
        exit;
    }
}

$page = [
    'title'       => 'Contact — Mori Capital',
    'description' => 'Reach our Malta office. Mori Capital Management Ltd., Regent House, Sliema.',
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.contact')],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<div class="our-services" style="padding:70px 0;">
    <div class="container">
        <div class="row" style="gap:0;">
            <!-- Info -->
            <div class="col-lg-5" style="padding-right:30px;">
                <div class="section-title">
                    <span class="section-sub-title wow fadeInUp">Get in touch</span>
                    <h2 style="font-size:clamp(24px,2.6vw,32px);">We'd love to hear from you</h2>
                </div>
                <p style="font-size:15px;color:var(--mori-text-soft,#5A6B7B);line-height:1.7;">Investors, intermediaries and journalists can reach us via the form, by phone or by email. Office hours are weekdays 09:00–18:00 CET.</p>

                <div style="margin-top:30px;display:flex;flex-direction:column;gap:18px;">
                    <div style="display:flex;align-items:flex-start;gap:14px;">
                        <div style="width:42px;height:42px;border-radius:8px;background:rgba(26,188,156,.12);color:var(--accent-color,#1ABC9C);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fa-solid fa-location-dot"></i></div>
                        <div>
                            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;font-weight:600;color:var(--mori-muted,#7A8B99);margin-bottom:3px;">Address</div>
                            <div style="font-size:14px;color:var(--primary-color,#1B3A5C);line-height:1.55;"><?= nl2br(e(setting('contact_address'))) ?></div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:14px;">
                        <div style="width:42px;height:42px;border-radius:8px;background:rgba(26,188,156,.12);color:var(--accent-color,#1ABC9C);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fa-solid fa-phone-volume"></i></div>
                        <div>
                            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;font-weight:600;color:var(--mori-muted,#7A8B99);margin-bottom:3px;">Phone</div>
                            <a href="tel:<?= e(preg_replace('/\s+/', '', setting('contact_phone',''))) ?>" style="font-size:15px;color:var(--primary-color,#1B3A5C);text-decoration:none;font-weight:600;"><?= e(setting('contact_phone')) ?></a>
                        </div>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:14px;">
                        <div style="width:42px;height:42px;border-radius:8px;background:rgba(26,188,156,.12);color:var(--accent-color,#1ABC9C);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fa-regular fa-envelope"></i></div>
                        <div>
                            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;font-weight:600;color:var(--mori-muted,#7A8B99);margin-bottom:3px;">Email</div>
                            <a href="mailto:<?= e(setting('contact_email')) ?>" style="font-size:15px;color:var(--primary-color,#1B3A5C);text-decoration:none;font-weight:600;"><?= e(setting('contact_email')) ?></a>
                        </div>
                    </div>
                </div>

                <div style="margin-top:30px;border-top:1px solid var(--mori-border,#E1E7EE);padding-top:24px;">
                    <span style="display:inline-flex;align-items:center;gap:10px;background:var(--mori-bg-soft,#F5F7FA);padding:10px 16px;border-radius:6px;font-size:12px;color:var(--primary-color,#1B3A5C);font-weight:600;"><i class="fa-solid fa-shield-halved" style="color:var(--accent-color,#1ABC9C);"></i> Regulated by MFSA · Firm Reference <?= e(setting('mfsa_license', 'C66999')) ?></span>
                </div>
            </div>

            <!-- Form -->
            <div class="col-lg-7" id="contact-form">
                <form method="post" style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:14px;padding:34px 36px;">
                    <?= Csrf::field() ?>
                    <!-- Honeypot -->
                    <div style="position:absolute;left:-9999px;" aria-hidden="true">
                        <label>Website</label>
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <?php if ($ok = flash('contact_ok')): ?>
                    <div style="background:#E8F8F4;border:1px solid var(--accent-color,#1ABC9C);color:#0F6B5C;padding:14px 16px;border-radius:8px;margin-bottom:18px;font-size:14px;">
                        <i class="fa-solid fa-circle-check" style="margin-right:8px;"></i><?= e($ok) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($err = flash('contact_error')): ?>
                    <div style="background:#FDECEC;border:1px solid #E57373;color:#8B2C2C;padding:14px 16px;border-radius:8px;margin-bottom:18px;font-size:14px;">
                        <i class="fa-solid fa-triangle-exclamation" style="margin-right:8px;"></i><?= e($err) ?>
                    </div>
                    <?php endif; ?>

                    <div class="row" style="margin-bottom:16px;">
                        <div class="col-md-6" style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;font-weight:600;color:var(--primary-color,#1B3A5C);margin-bottom:6px;"><?= e(t('contact.form.name')) ?> *</label>
                            <input type="text" name="name" required maxlength="120" style="width:100%;padding:11px 13px;border:1px solid var(--mori-border,#E1E7EE);border-radius:6px;font-family:inherit;font-size:14px;">
                        </div>
                        <div class="col-md-6" style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;font-weight:600;color:var(--primary-color,#1B3A5C);margin-bottom:6px;"><?= e(t('contact.form.email')) ?> *</label>
                            <input type="email" name="email" required maxlength="190" style="width:100%;padding:11px 13px;border:1px solid var(--mori-border,#E1E7EE);border-radius:6px;font-family:inherit;font-size:14px;">
                        </div>
                    </div>

                    <label style="display:block;font-size:12px;font-weight:600;color:var(--primary-color,#1B3A5C);margin-bottom:6px;"><?= e(t('contact.form.subject')) ?></label>
                    <input type="text" name="subject" maxlength="255" style="width:100%;padding:11px 13px;border:1px solid var(--mori-border,#E1E7EE);border-radius:6px;font-family:inherit;font-size:14px;margin-bottom:14px;">

                    <label style="display:block;font-size:12px;font-weight:600;color:var(--primary-color,#1B3A5C);margin-bottom:6px;"><?= e(t('contact.form.message')) ?> *</label>
                    <textarea name="message" required rows="6" maxlength="5000" style="width:100%;padding:11px 13px;border:1px solid var(--mori-border,#E1E7EE);border-radius:6px;font-family:inherit;font-size:14px;resize:vertical;margin-bottom:18px;"></textarea>

                    <button type="submit" class="btn-default" style="background:var(--accent-color,#1ABC9C) !important;color:#fff !important;border-color:var(--accent-color,#1ABC9C) !important;width:100%;padding:14px;">
                        <i class="fa-regular fa-paper-plane"></i> <?= e(t('contact.form.send')) ?>
                    </button>

                    <p style="font-size:11.5px;color:var(--mori-muted,#7A8B99);margin-top:14px;text-align:center;line-height:1.5;">
                        By submitting this form you confirm you have read the <a href="<?= asset('privacy.php') ?>" style="color:var(--accent-color,#1ABC9C);">Privacy Policy</a>.
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Map (OpenStreetMap embed — Sliema) -->
<div style="height:380px;background:var(--mori-bg-soft,#F5F7FA);">
    <iframe src="https://www.openstreetmap.org/export/embed.html?bbox=14.502%2C35.910%2C14.512%2C35.918&layer=mapnik&marker=35.9137%2C14.5070" style="border:0;width:100%;height:100%;" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Mori Capital — Sliema, Malta"></iframe>
</div>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
