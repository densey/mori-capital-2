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

// ---- AJAX: Get recipient list -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_recipients') {
    header('Content-Type: application/json');
    if (!Csrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null)) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'CSRF token invalid']); exit;
    }

    $locale   = $_POST['locale'] ?? 'all';
    $testOnly = !empty($_POST['test_only']);

    if ($testOnly) {
        $recipients = [['id' => 0, 'email' => Auth::user()['email']]];
    } else {
        $where = "status IN ('confirmed','pending')";
        $params = [];
        if ($locale === 'en' || $locale === 'de') {
            $where .= ' AND locale = :loc';
            $params['loc'] = $locale;
        }
        $recipients = $db->fetchAll("SELECT id, email FROM newsletter_subscribers WHERE $where ORDER BY created_at", $params);
    }

    if (empty($recipients)) {
        echo json_encode(['ok' => false, 'error' => 'No subscribers found for the selected filter.']); exit;
    }

    // Return only IDs and emails
    $list = array_map(function($r) { return ['id' => (int)$r['id'], 'email' => $r['email']]; }, $recipients);
    echo json_encode(['ok' => true, 'recipients' => $list, 'total' => count($list)]);
    exit;
}

// ---- AJAX: Send a batch of emails -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_batch') {
    header('Content-Type: application/json');
    if (!Csrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null)) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'CSRF token invalid']); exit;
    }

    $subject  = trim($_POST['subject'] ?? '');
    $body     = $_POST['body'] ?? '';
    $template = $_POST['template'] ?? 'branded';
    $emails   = json_decode($_POST['emails'] ?? '[]', true);

    if ($subject === '' || $body === '' || empty($emails)) {
        echo json_encode(['ok' => false, 'error' => 'Missing required fields']); exit;
    }

    $siteName = setting('site_title', 'Mori Capital Management');
    $siteUrl  = \Mori\Config::get('SITE_URL', 'https://mori-capital.com');

    $sent = 0; $failed = 0; $errors = [];
    foreach ($emails as $email) {
        $email = trim($email);
        if (!$email) continue;
        $htmlBody = buildNewsletterHtml($template, $subject, $body, $siteName, $siteUrl, $email);
        $ok = Mail::send($email, $subject, $htmlBody, true);
        if ($ok) { $sent++; } else { $failed++; $errors[] = $email; }
        usleep(100000); // 100ms between emails to avoid throttling
    }

    echo json_encode(['ok' => true, 'sent' => $sent, 'failed' => $failed, 'errors' => $errors]);
    exit;
}

// ---- AJAX: Log completed send -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'log_complete') {
    header('Content-Type: application/json');
    if (!Csrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null)) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'CSRF']); exit;
    }

    $subject  = trim($_POST['subject'] ?? '');
    $locale   = $_POST['locale'] ?? 'all';
    $testOnly = !empty($_POST['test_only']);
    $sent     = (int)($_POST['sent'] ?? 0);
    $failed   = (int)($_POST['failed'] ?? 0);

    AuditLog::log(Auth::userId(), 'newsletter_sent', 'newsletter_subscribers', null,
        ($testOnly ? 'TEST ' : '') . "Subject: $subject | Sent: $sent | Failed: $failed | Locale: $locale"
    );
    echo json_encode(['ok' => true]);
    exit;
}

// ---- Legacy SEND (POST) — kept as fallback for test emails ------------------
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
                    <button class="a-btn lg" type="button" id="btnSendAll" style="width:100%;justify-content:center;">
                        <i class="fa-solid fa-paper-plane"></i> Send to all subscribers
                    </button>

                    <!-- Progress UI (hidden until sending) -->
                    <div id="nlProgress" style="display:none;margin-top:14px;">
                        <div style="background:#E1E7EE;border-radius:6px;height:18px;overflow:hidden;position:relative;">
                            <div id="nlProgressBar" style="background:linear-gradient(90deg,#1ABC9C,#16A085);height:100%;width:0%;border-radius:6px;transition:width .3s ease;"></div>
                            <span id="nlProgressPct" style="position:absolute;top:0;left:0;right:0;text-align:center;font-size:11px;font-weight:700;line-height:18px;color:#fff;text-shadow:0 0 3px rgba(0,0,0,.3);">0%</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:12px;color:var(--a-muted);">
                            <span><i class="fa-solid fa-check" style="color:#1ABC9C"></i> Sent: <strong id="nlSent">0</strong></span>
                            <span><i class="fa-solid fa-xmark" style="color:#E74C3C"></i> Failed: <strong id="nlFailed">0</strong></span>
                            <span><i class="fa-solid fa-clock"></i> Left: <strong id="nlRemaining">0</strong></span>
                        </div>
                        <p id="nlStatusText" style="font-size:11px;color:var(--a-muted);margin-top:8px;text-align:center;">
                            Preparing...
                        </p>
                    </div>
                    <!-- Final results (hidden until complete) -->
                    <div id="nlResults" style="display:none;margin-top:14px;"></div>

                    <p style="font-size:11px;color:var(--a-muted);margin-top:12px;text-align:center;">
                        Emails are sent in batches of 10 with progress tracking.
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

<script>
(function() {
    var csrfToken = <?= json_encode(Csrf::token()) ?>;
    var BATCH_SIZE = 10;
    var btnSendAll = document.getElementById('btnSendAll');
    var progressWrap = document.getElementById('nlProgress');
    var progressBar = document.getElementById('nlProgressBar');
    var progressPct = document.getElementById('nlProgressPct');
    var elSent = document.getElementById('nlSent');
    var elFailed = document.getElementById('nlFailed');
    var elRemaining = document.getElementById('nlRemaining');
    var statusText = document.getElementById('nlStatusText');
    var resultsDiv = document.getElementById('nlResults');
    var sending = false;

    btnSendAll.addEventListener('click', function() {
        if (sending) return;
        var localeSelect = document.querySelector('[name=locale]');
        var localeLabel = localeSelect.selectedOptions[0].textContent;
        if (!confirm('Send this newsletter to ' + localeLabel + '?')) return;

        var subject = document.querySelector('[name=subject]').value.trim();
        var body = (typeof tinymce !== 'undefined' && tinymce.activeEditor) ? tinymce.activeEditor.getContent() : document.querySelector('[name=body]').value;
        var template = document.querySelector('[name=template]').value;
        var locale = localeSelect.value;

        if (!subject || !body) { alert('Subject and body are required.'); return; }

        sending = true;
        btnSendAll.disabled = true;
        btnSendAll.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
        progressWrap.style.display = 'block';
        resultsDiv.style.display = 'none';
        statusText.textContent = 'Fetching recipient list...';

        // Step 1: Get recipients
        var fd = new FormData();
        fd.append('action', 'get_recipients');
        fd.append('_csrf', csrfToken);
        fd.append('locale', locale);

        fetch(window.location.pathname, { method: 'POST', headers: {'X-CSRF-Token': csrfToken}, body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) { throw new Error(data.error || 'Failed to get recipients'); }
            return sendInBatches(data.recipients, subject, body, template, locale);
        })
        .catch(function(err) {
            statusText.textContent = 'Error: ' + err.message;
            resetSendButton();
        });
    });

    function sendInBatches(recipients, subject, body, template, locale) {
        var total = recipients.length;
        var totalSent = 0, totalFailed = 0, failedEmails = [];
        var batchIndex = 0;

        elRemaining.textContent = total;
        statusText.textContent = 'Sending to ' + total + ' recipients in batches of ' + BATCH_SIZE + '...';

        function sendNextBatch() {
            var start = batchIndex * BATCH_SIZE;
            if (start >= total) {
                // All done — log completion and show results
                logComplete(subject, locale, false, totalSent, totalFailed);
                showResults(totalSent, totalFailed, failedEmails);
                return;
            }

            var batch = recipients.slice(start, start + BATCH_SIZE);
            var emails = batch.map(function(r) { return r.email; });
            var batchNum = batchIndex + 1;
            var totalBatches = Math.ceil(total / BATCH_SIZE);
            statusText.textContent = 'Sending batch ' + batchNum + ' of ' + totalBatches + '...';

            var fd = new FormData();
            fd.append('action', 'send_batch');
            fd.append('_csrf', csrfToken);
            fd.append('subject', subject);
            fd.append('body', body);
            fd.append('template', template);
            fd.append('emails', JSON.stringify(emails));

            fetch(window.location.pathname, { method: 'POST', headers: {'X-CSRF-Token': csrfToken}, body: fd })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                if (!result.ok) { throw new Error(result.error || 'Batch failed'); }
                totalSent += result.sent;
                totalFailed += result.failed;
                if (result.errors) failedEmails = failedEmails.concat(result.errors);

                // Update UI
                var processed = Math.min(start + BATCH_SIZE, total);
                var pct = Math.round((processed / total) * 100);
                progressBar.style.width = pct + '%';
                progressPct.textContent = pct + '%';
                elSent.textContent = totalSent;
                elFailed.textContent = totalFailed;
                elRemaining.textContent = Math.max(0, total - processed);

                batchIndex++;
                sendNextBatch();
            })
            .catch(function(err) {
                // On network error for this batch, count remaining as failed and continue
                var batchSize = batch.length;
                totalFailed += batchSize;
                failedEmails = failedEmails.concat(emails);
                elFailed.textContent = totalFailed;
                statusText.textContent = 'Batch ' + batchNum + ' failed (' + err.message + '), continuing...';

                var processed = Math.min(start + BATCH_SIZE, total);
                var pct = Math.round((processed / total) * 100);
                progressBar.style.width = pct + '%';
                progressPct.textContent = pct + '%';
                elRemaining.textContent = Math.max(0, total - processed);

                batchIndex++;
                sendNextBatch();
            });
        }

        sendNextBatch();
    }

    function logComplete(subject, locale, testOnly, sent, failed) {
        var fd = new FormData();
        fd.append('action', 'log_complete');
        fd.append('_csrf', csrfToken);
        fd.append('subject', subject);
        fd.append('locale', locale);
        fd.append('sent', sent);
        fd.append('failed', failed);
        if (testOnly) fd.append('test_only', '1');
        fetch(window.location.pathname, { method: 'POST', headers: {'X-CSRF-Token': csrfToken}, body: fd }).catch(function(){});
    }

    function showResults(sent, failed, failedEmails) {
        progressBar.style.width = '100%';
        progressPct.textContent = '100%';
        elRemaining.textContent = '0';
        statusText.textContent = 'Complete!';

        var cls = failed > 0 ? 'a-alert error' : 'a-alert ok';
        var icon = failed > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check';
        var html = '<div class="' + cls + '"><i class="fa-solid ' + icon + '"></i> Newsletter sent: <strong>' + sent + '</strong> delivered, <strong>' + failed + '</strong> failed.';
        if (failedEmails.length > 0) {
            html += '<br><small style="margin-top:6px;display:block">Failed addresses: ' + failedEmails.join(', ') + '</small>';
        }
        html += '</div>';
        resultsDiv.innerHTML = html;
        resultsDiv.style.display = 'block';
        resetSendButton();
    }

    function resetSendButton() {
        sending = false;
        btnSendAll.disabled = false;
        btnSendAll.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send to all subscribers';
    }
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
