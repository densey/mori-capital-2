<?php
/**
 * Mail — lightweight SMTP mailer (no external dependencies).
 *
 * Reads SMTP credentials from .env. Falls back to PHP mail() if SMTP
 * is not configured. Supports HTML bodies and TLS/SSL.
 *
 * Usage:
 *   Mail::send('to@example.com', 'Subject', '<p>HTML body</p>');
 *   Mail::send('to@example.com', 'Subject', 'Plain text', false);
 */
declare(strict_types=1);

namespace Mori;

final class Mail
{
    public static function send(string $to, string $subject, string $body, bool $isHtml = true, ?string $replyTo = null): bool
    {
        $host = Config::get('SMTP_HOST', '');
        $port = (int) Config::get('SMTP_PORT', '587');
        $user = Config::get('SMTP_USER', '');
        $pass = Config::get('SMTP_PASS', '');
        $from = Config::get('SMTP_FROM', 'info@mori-capital.com');
        $fromName = Config::get('SMTP_FROM_NAME', 'Mori Capital Management');
        $secure = strtolower((string) Config::get('SMTP_SECURE', 'tls'));
        $replyAddr = ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) ? $replyTo : $from;

        // If SMTP is not configured, try PHP mail() as fallback
        if ($host === '') {
            $headers  = "From: {$fromName} <{$from}>\r\n";
            $headers .= "Reply-To: {$replyAddr}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= $isHtml
                ? "Content-Type: text/html; charset=UTF-8\r\n"
                : "Content-Type: text/plain; charset=UTF-8\r\n";
            return @mail($to, $subject, $body, $headers);
        }

        // SMTP send
        try {
            $prefix = ($secure === 'ssl') ? 'ssl://' : '';
            $socket = @stream_socket_client(
                $prefix . $host . ':' . $port,
                $errno, $errstr, 15,
                STREAM_CLIENT_CONNECT,
                stream_context_create(['ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                    'allow_self_signed'=> true,
                ]])
            );
            if (!$socket) {
                error_log("Mail SMTP connect failed: $errstr ($errno)");
                return false;
            }

            $read = function () use ($socket): string {
                $response = '';
                while ($line = fgets($socket, 515)) {
                    $response .= $line;
                    if (isset($line[3]) && $line[3] === ' ') break;
                }
                return $response;
            };

            $cmd = function (string $command) use ($socket, $read): string {
                fwrite($socket, $command . "\r\n");
                return $read();
            };

            $read(); // greeting

            $cmd('EHLO ' . gethostname());

            if ($secure === 'tls') {
                $cmd('STARTTLS');
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
                $cmd('EHLO ' . gethostname());
            }

            if ($user !== '') {
                $cmd('AUTH LOGIN');
                $cmd(base64_encode($user));
                $authResult = $cmd(base64_encode($pass));
                if (!str_starts_with(trim($authResult), '235')) {
                    error_log("Mail SMTP auth failed: $authResult");
                    fclose($socket);
                    return false;
                }
            }

            $cmd("MAIL FROM:<{$from}>");
            $cmd("RCPT TO:<{$to}>");
            $cmd('DATA');

            $contentType = $isHtml ? 'text/html' : 'text/plain';
            $date = date('r');
            $msgId = '<' . bin2hex(random_bytes(8)) . '@' . parse_url((string)Config::get('SITE_URL', ''), PHP_URL_HOST) . '>';

            $message  = "Date: {$date}\r\n";
            $message .= "From: {$fromName} <{$from}>\r\n";
            $message .= "Reply-To: {$replyAddr}\r\n";
            $message .= "To: {$to}\r\n";
            $message .= "Subject: {$subject}\r\n";
            $message .= "Message-ID: {$msgId}\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: {$contentType}; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n";
            $message .= "\r\n";
            $message .= $body . "\r\n";
            $message .= ".";

            $result = $cmd($message);
            $cmd('QUIT');
            fclose($socket);

            $ok = str_starts_with(trim($result), '250');
            if (!$ok) error_log("Mail SMTP send failed: $result");
            return $ok;
        } catch (\Throwable $e) {
            error_log("Mail exception: " . $e->getMessage());
            return false;
        }
    }

    /** Send a templated HTML email with Mori branding. */
    public static function sendTemplate(string $to, string $subject, string $heading, string $bodyHtml, ?string $replyTo = null): bool
    {
        $siteName = setting('site_title', 'Mori Capital Management');
        $siteUrl  = Config::get('SITE_URL', 'https://mori-capital.com');

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;font-family:Inter,Arial,sans-serif;background:#F5F7FA;">'
              . '<div style="max-width:600px;margin:0 auto;padding:40px 20px;">'
              . '<div style="background:#fff;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.06);overflow:hidden;">'
              . '<div style="background:#1B3A5C;padding:24px 30px;text-align:center;">'
              . '<span style="color:#fff;font-size:18px;font-weight:700;letter-spacing:0.02em;">' . htmlspecialchars($siteName) . '</span>'
              . '</div>'
              . '<div style="padding:32px 30px;">'
              . '<h1 style="color:#1B3A5C;font-size:22px;margin:0 0 18px;line-height:1.3;">' . htmlspecialchars($heading) . '</h1>'
              . '<div style="color:#5A6B7B;font-size:15px;line-height:1.65;">' . $bodyHtml . '</div>'
              . '</div>'
              . '<div style="padding:18px 30px;border-top:1px solid #E1E7EE;font-size:12px;color:#7A8B99;text-align:center;">'
              . htmlspecialchars($siteName) . ' &middot; <a href="' . htmlspecialchars($siteUrl) . '" style="color:#1ABC9C;">' . htmlspecialchars($siteUrl) . '</a>'
              . '</div></div></div></body></html>';

        return self::send($to, $subject, $html, true, $replyTo);
    }

    /** Quick test — sends a test email to the given address. */
    public static function test(string $to): array
    {
        $ok = self::sendTemplate(
            $to,
            'SMTP Test — Mori Capital CMS',
            'SMTP Configuration Test',
            '<p>If you are reading this, your SMTP settings are working correctly.</p>'
            . '<p style="color:#7A8B99;font-size:13px;">Sent at ' . date('Y-m-d H:i:s T') . '</p>'
        );
        return ['ok' => $ok, 'message' => $ok ? 'Test email sent to ' . $to : 'Send failed — check SMTP settings and server error log'];
    }
}
