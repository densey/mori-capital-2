<?php
/**
 * Newsletter — compose, preview and send bulk emails to subscribers.
 * Supports multiple templates, locale filtering and send tracking.
 */
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use Mori\AuditLog;
use Mori\Mail;
use function Mori\e;
use function Mori\asset;
use function Mori\flash;
use function Mori\setting;
use function Mori\redirect;

Auth::requireLogin();
$db = Database::instance();

// ---- SEND (POST) --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    Csrf::requireValid();

    $subject  = trim($_POST['subject'] ?? '');
    $body     = $_POST['body'] ?? '';
    $template = $_POST['template'] ?? 'branded';
    $locale   = $_POST['locale'] ?? 'all';
    $testOnly = !empty($_POST['test_only']);

    if ($subject === '' || $body === '') {
        flash('error', 'Subject and body are required.');
        redirect(asset('admin/newsletter-send.php'));
    }

    // Get recipients
    if ($testOnly) {
        $recipients = [['email' => Auth::user()['email'], 'locale' => 'en']];
    } else {
        $where = "status IN ('confirmed','pending')";
        $params = [];
        if ($locale === 'en' || $locale === 'de') {
            $where .= ' AND locale = :loc';
            $params['loc'] = $locale;
        }
        $recipients = $db->fetchAll("SELECT email, locale FROM newsletter_subscribers WHERE $where ORDER BY created_at", $params);
    }

    if (empty($recipients)) {
        flash('error', 'No subscribers found for the selected filter.');
        redirect(asset('admin/newsletter-send.php'));
    }

    // Build email content based on template
    $siteName = setting('site_title', 'Mori Capital Management');
    $siteUrl  = \Mori\Config::get('SITE_URL', 'https://mori-capital.com');

    $sent = 0; $failed = 0;
    foreach ($recipients as $r) {
        $htmlBody = buildNewsletterHtml($template, $subject, $body, $siteName, $siteUrl, $r['email']);
        $ok = Mail::send($r['email'], $subject, $htmlBody, true);
        if ($ok) $sent++; else $failed++;
        usleep(100000); // 100ms between emails to avoid throttling
    }

    AuditLog::log(Auth::userId(), 'newsletter_sent', 'newsletter_subscribers', null,
        ($testOnly ? 'TEST ' : '') . "Subject: $subject | Sent: $sent | Failed: $failed | Locale: $locale"
    );

    if ($testOnly) {
        flash('ok', "Test email sent to " . Auth::user()['email'] . ".");
    } else {
        flash('ok', "Newsletter sent: $sent delivered, $failed failed" . ($failed ? ' — check server error log.' : '.'));
    }
    redirect(asset('admin/newsletter-send.php'));
}

function buildNewsletterHtml(string $template, string $subject, string $body, string $siteName, string $siteUrl, string $recipientEmail): string {
    $unsubLink = $siteUrl . '/api/newsletter.php?action=unsubscribe&email=' . urlencode($recipientEmail);

    if ($template === 'minimal') {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:40px 20px;font-family:Inter,Arial,sans-serif;color:#2C3E50;background:#F5F7FA;">'
             . '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:36px 32px;box-shadow:0 2px 8px rgba(0,0,0,.04);">'
             . $body
             . '</div>'
             . '<div style="max-width:600px;margin:16px auto;text-align:center;font-size:11px;color:#7A8B99;">'
             . htmlspecialchars($siteName) . ' &middot; <a href="' . htmlspecialchars($unsubLink) . '" style="color:#7A8B99;">Unsubscribe</a>'
             . '</div></body></html>';
    }

    if ($template === 'plain') {
        return strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $body))
             . "\n\n---\n" . $siteName . "\nUnsubscribe: " . $unsubLink;
    }

    // 'branded' (default) — full Mori branding
    return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
         . '<body style="margin:0;padding:0;font-family:Inter,Arial,Helvetica,sans-serif;background:#F5F7FA;">'
         . '<div style="max-width:640px;margin:0 auto;padding:32px 16px;">'

         // Header
         . '<div style="background:linear-gradient(135deg,#1B3A5C 0%,#122842 100%);border-radius:12px 12px 0 0;padding:28px 32px;text-align:center;">'
         . '<img src="' . htmlspecialchars($siteUrl) . '/assets/images/mori-capital-logo.fw.png" alt="' . htmlspecialchars($siteName) . '" style="height:48px;display:inline-block;" />'
         . '</div>'

         // Accent bar
         . '<div style="height:4px;background:#1ABC9C;"></div>'

         // Body
         . '<div style="background:#fff;padding:36px 32px;border-left:1px solid #E1E7EE;border-right:1px solid #E1E7EE;">'
         . '<h1 style="color:#1B3A5C;font-size:22px;margin:0 0 20px;line-height:1.3;letter-spacing:-0.01em;">' . htmlspecialchars($subject) . '</h1>'
         . '<div style="color:#5A6B7B;font-size:15px;line-height:1.7;">'
         . $body
         . '</div>'
         . '</div>'

         // CTA strip
         . '<div style="background:#F5F7FA;padding:20px 32px;text-align:center;border-left:1px solid #E1E7EE;border-right:1px solid #E1E7EE;">'
         . '<a href="' . htmlspecialchars($siteUrl) . '" style="display:inline-block;background:#1ABC9C;color:#fff;padding:12px 28px;border-radius:6px;font-weight:600;font-size:14px;text-decoration:none;">Visit ' . htmlspecialchars($siteName) . '</a>'
         . '</div>'

         // Footer
         . '<div style="background:#1B3A5C;border-radius:0 0 12px 12px;padding:22px 32px;text-align:center;">'
         . '<p style="color:rgba(255,255,255,.7);font-size:12px;margin:0 0 8px;">'
         . htmlspecialchars($siteName) . ' &middot; Regent House, Office 35, Bisazza Street, Sliema SLM 1640, Malta'
         . '</p>'
         . '<p style="color:rgba(255,255,255,.5);font-size:11px;margin:0;">'
         . 'You received this because you subscribed to our mailing list. '
         . '<a href="' . htmlspecialchars($unsubLink) . '" style="color:#1ABC9C;">Unsubscribe</a>'
         . '</p>'
         . '</div>'

         . '</div></body></html>';
}

// ---- Stats ---------------------------------------------------------------
$totalSubs   = (int) $db->fetchColumn("SELECT COUNT(*) FROM newsletter_subscribers WHERE status IN ('confirmed','pending')");
$enSubs      = (int) $db->fetchColumn("SELECT COUNT(*) FROM newsletter_subscribers WHERE status IN ('confirmed','pending') AND locale='en'");
$deSubs      = (int) $db->fetchColumn("SELECT COUNT(*) FROM newsletter_subscribers WHERE status IN ('confirmed','pending') AND locale='de'");

$adminPage = ['title' => 'Send Newsletter', 'crumb' => 'Compose and send bulk email to subscribers', 'tinymce' => true];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><i class="fa-solid fa-circle-check"></i> <?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert error"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($err) ?></div><?php endif; ?>

<!-- Stats -->
<div class="a-grid a-grid-3" style="margin-bottom:22px;">
    <div class="a-stat">
        <div class="a-stat__icon"><i class="fa-regular fa-paper-plane"></i></div>
        <div><div class="a-stat__lbl">Total subscribers</div><div class="a-stat__val"><?= e($totalSubs) ?></div></div>
    </div>
    <div class="a-stat">
        <div class="a-stat__icon navy"><i class="fa-solid fa-globe"></i></div>
        <div><div class="a-stat__lbl">English</div><div class="a-stat__val"><?= e($enSubs) ?></div></div>
    </div>
    <div class="a-stat">
        <div class="a-stat__icon"><i class="fa-solid fa-globe"></i></div>
        <div><div class="a-stat__lbl">Deutsch</div><div class="a-stat__val"><?= e($deSubs) ?></div></div>
    </div>
</div>

<!-- Compose -->
<form method="post" class="a-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="send">

    <div class="a-grid" style="grid-template-columns:2fr 1fr;gap:22px;">
        <div>
            <div class="a-card">
                <div class="a-card__head"><h2><i class="fa-solid fa-pen-to-square"></i> Compose</h2></div>
                <div class="a-card__body">
                    <label>Subject line *</label>
                    <input type="text" name="subject" required placeholder="e.g. Mori Capital — Q1 2026 EEMEA Outlook" value="<?= e($_POST['subject'] ?? '') ?>">

                    <label>Email body * (HTML — use the toolbar to format)</label>
                    <textarea name="body" class="wysiwyg" rows="18"><?= e($_POST['body'] ?? '<p>Dear Investor,</p>
<p>We are pleased to share our latest EEMEA market commentary and fund updates.</p>
<h2>Key Highlights</h2>
<ul>
<li>Eastern European equities continued their re-rating trend</li>
<li>Turkish market offers selective opportunities</li>
<li>Our portfolio remains positioned for long-term growth</li>
</ul>
<p>For the full quarterly factsheet and detailed performance data, please visit our <strong>Document Hub</strong>.</p>
<p>Best regards,<br>The Mori Capital Team</p>') ?></textarea>
                </div>
            </div>
        </div>

        <aside>
            <!-- Template selection -->
            <div class="a-card" style="margin-bottom:18px;">
                <div class="a-card__head"><h2><i class="fa-solid fa-palette"></i> Template</h2></div>
                <div class="a-card__body">
                    <label>Email template</label>
                    <select name="template">
                        <option value="branded" selected>Branded (Mori navy header + teal accent + footer)</option>
                        <option value="minimal">Minimal (clean white card, no header)</option>
                        <option value="plain">Plain text (no HTML styling)</option>
                    </select>
                    <div class="hint" style="margin-top:8px;">
                        <strong>Branded</strong>: navy header with Mori logo, teal accent bar, CTA button, branded footer with unsubscribe link.<br>
                        <strong>Minimal</strong>: clean white card, unsubscribe link at bottom.<br>
                        <strong>Plain</strong>: text-only, no images.
                    </div>
                </div>
            </div>

            <!-- Recipients -->
            <div class="a-card" style="margin-bottom:18px;">
                <div class="a-card__head"><h2><i class="fa-solid fa-users"></i> Recipients</h2></div>
                <div class="a-card__body">
                    <label>Send to</label>
                    <select name="locale">
                        <option value="all">All subscribers (<?= e($totalSubs) ?>)</option>
                        <option value="en">English only (<?= e($enSubs) ?>)</option>
                        <option value="de">Deutsch only (<?= e($deSubs) ?>)</option>
                    </select>
                </div>
            </div>

            <!-- Actions -->
            <div class="a-card">
                <div class="a-card__head"><h2><i class="fa-solid fa-rocket"></i> Send</h2></div>
                <div class="a-card__body">
                    <div class="a-alert info" style="font-size:12.5px;margin-bottom:14px;">
                        <strong>Tip:</strong> Always send a test first to verify the layout and content before sending to all subscribers.
                    </div>

                    <button class="a-btn ghost" type="submit" name="test_only" value="1" style="width:100%;justify-content:center;margin-bottom:10px;">
                        <i class="fa-solid fa-flask"></i> Send test to my email
                    </button>
                    <button class="a-btn lg" type="submit" style="width:100%;justify-content:center;" onclick="return confirm('Send this newsletter to ' + document.querySelector('[name=locale]').selectedOptions[0].textContent + '?')">
                        <i class="fa-solid fa-paper-plane"></i> Send to all subscribers
                    </button>

                    <p style="font-size:11px;color:var(--a-muted);margin-top:12px;text-align:center;">
                        Emails are sent one-by-one with 100ms delay to avoid spam filters.
                        Large lists may take a few minutes.
                    </p>
                </div>
            </div>
        </aside>
    </div>
</form>

<!-- Template preview -->
<div class="a-card" style="margin-top:22px;">
    <div class="a-card__head"><h2><i class="fa-solid fa-eye"></i> Template Preview — Branded</h2></div>
    <div class="a-card__body" style="background:#F5F7FA;padding:30px;">
        <div style="max-width:640px;margin:0 auto;">
            <!-- Preview of the branded template -->
            <div style="background:linear-gradient(135deg,#1B3A5C 0%,#122842 100%);border-radius:12px 12px 0 0;padding:28px 32px;text-align:center;">
                <img src="<?= \Mori\asset('assets/images/mori-capital-logo.fw.png') ?>" alt="Mori" style="height:42px;">
            </div>
            <div style="height:4px;background:#1ABC9C;"></div>
            <div style="background:#fff;padding:32px;border-left:1px solid #E1E7EE;border-right:1px solid #E1E7EE;">
                <h1 style="color:#1B3A5C;font-size:20px;margin:0 0 16px;">Your subject line appears here</h1>
                <p style="color:#5A6B7B;font-size:14px;line-height:1.65;">Your email body content appears here. HTML formatting (bold, lists, links, headings) from the editor is preserved.</p>
            </div>
            <div style="background:#F5F7FA;padding:18px 32px;text-align:center;border-left:1px solid #E1E7EE;border-right:1px solid #E1E7EE;">
                <span style="display:inline-block;background:#1ABC9C;color:#fff;padding:10px 24px;border-radius:6px;font-weight:600;font-size:13px;">Visit Mori Capital Management</span>
            </div>
            <div style="background:#1B3A5C;border-radius:0 0 12px 12px;padding:20px 32px;text-align:center;">
                <p style="color:rgba(255,255,255,.65);font-size:11px;margin:0;">Mori Capital Management &middot; Sliema, Malta &middot; <span style="color:#1ABC9C;">Unsubscribe</span></p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
