<?php
declare(strict_types=1);

// ------------------------------------------------------------------
// Outgoing email settings — CHANGE THESE BEFORE YOU DEPLOY.
// mail() works on most shared cPanel hosts for addresses at your own
// domain. The "From" address must be on the domain the site runs on,
// otherwise the host may silently reject it.
// ------------------------------------------------------------------

// Where "From: <Site Name> <address>" notifications and replies appear to come from.
const SITE_MAIL_FROM = 'no-reply@sujoodandstrategy.com';
const SITE_MAIL_NAME = 'Between Sujood & Strategy';

// Where the "New subscriber / New gift request" alerts go.
const SITE_ADMIN_EMAIL = 'admin@example.com'; // <-- replace with your inbox

function e_html(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Renders the branded HTML email shell (book, wine + cream tones).
 * $parts supports: title, subtitle, content (body HTML), details (key=>value), note (footer).
 */
function email_brand(array $parts): string
{
    $title    = (string)($parts['title'] ?? '');
    $subtitle = (string)($parts['subtitle'] ?? '');
    $content  = (string)($parts['content'] ?? '');
    $details  = (array)($parts['details'] ?? []);
    $note     = (string)($parts['note'] ?? '');

    $detailHtml = '';
    if ($details) {
        $rows = '';
        foreach ($details as $label => $value) {
            $rows .= '<tr>'
                . '<td style="padding:7px 0;color:#9c8f88;font-family:Arial,Helvetica,sans-serif;font-size:11px;text-transform:uppercase;letter-spacing:0.7px;vertical-align:top;">' . e_html((string)$label) . '</td>'
                . '<td style="padding:7px 0 7px 14px;color:#231d1a;font-family:Arial,Helvetica,sans-serif;font-size:14px;word-break:break-word;">' . nl2br(e_html((string)$value)) . '</td>'
                . '</tr>';
        }
        $detailHtml = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
            . ' style="border:1px solid #eadfd5;border-radius:10px;margin:10px 22px;">'
            . '<tr><td style="padding:14px 20px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
            . $rows
            . '</table>'
            . '</td></tr></table>';
    }

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;padding:0;background:#f4f1ec;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f1ec;padding:26px 14px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"'
        . ' style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 12px 34px rgba(35,29,26,0.10);">'
        . '<tr><td style="padding:34px 40px 24px;text-align:center;background:#4f1c2f;">'
        . '<div style="font-family:Georgia,\'Times New Roman\',serif;font-size:20px;color:#e8b3a6;letter-spacing:0.4px;font-style:italic;">Between Sujood</div>'
        . '<div style="font-family:Georgia,\'Times New Roman\',serif;font-size:26px;color:#ffffff;font-weight:bold;">&amp; Strategy</div>'
        . '<div style="border-top:1px solid rgba(255,255,255,0.25);width:56px;margin:14px auto 0;"></div>'
        . '</td></tr>'
        . '<tr><td style="padding:34px 40px 30px;">'
        . '<h1 style="font-family:Georgia,\'Times New Roman\',serif;font-size:23px;font-weight:bold;color:#4f1c2f;margin:0 0 4px;">' . e_html($title) . '</h1>'
        . ($subtitle !== '' ? '<p style="font-family:Georgia,\'Times New Roman\',serif;font-size:15px;font-style:italic;color:#d9705f;margin:0 0 22px;">' . e_html($subtitle) . '</p>' : '')
        . $detailHtml
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.75;color:#231d1a;">' . $content . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:22px 40px;border-top:1px solid #eee5dd;background:#fbf8f4;text-align:center;">'
        . ($note !== '' ? '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#9c8f88;line-height:1.7;">' . $note . '</div>' : '')
        . '<div style="font-family:Georgia,\'Times New Roman\',serif;font-size:13px;color:#b1832f;margin-top:8px;">Between Sujood &amp; Strategy &mdash; Rahmah Aderinoye</div>'
        . '</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</body></html>';
}

/** Plain-text twin of email_brand(), for mail clients that only show text. */
function email_plain_build(array $parts): string
{
    $lines = [];
    if (!empty($parts['title'])) {
        $lines[] = strtoupper((string)$parts['title']);
    }
    if (!empty($parts['subtitle'])) {
        $lines[] = (string)$parts['subtitle'];
    }
    $lines[] = str_repeat('=', 52);
    foreach (($parts['details'] ?? []) as $k => $v) {
        $lines[] = (string)$k . ': ' . (string)$v;
    }
    if (!empty($parts['details'])) {
        $lines[] = str_repeat('-', 52);
    }
    $body = (string)($parts['content'] ?? '');
    $body = str_replace(['<br>', '<br />', '</p>', '</div>', '<li>', '</li>'], ["\n", "\n", "\n\n", "\n", "  • ", "\n"], $body);
    $body = trim(strip_tags($body));
    if ($body !== '') {
        $lines[] = $body;
    }
    $lines[] = '';
    $lines[] = '— Between Sujood & Strategy, Rahmah Aderinoye';
    return implode("\n", $lines);
}

/**
 * Sends a multipart/alternative email (styled HTML + graceful plain text).
 * @return bool true if accepted by the mail system.
 */
function send_email(string $to, string $subject, string $htmlBody, ?string $plainBody = null): bool
{
    if ($plainBody === null) {
        $plainBody = strip_tags($htmlBody);
    }

    $b = '----=_su_' . md5(uniqid('', true));

    $headers = 'From: ' . SITE_MAIL_NAME . ' <' . SITE_MAIL_FROM . '>' . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/alternative; boundary="' . $b . "\"\r\n";
    $headers .= 'X-Mailer: PHP/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . "\r\n";

    $msg = 'This is a multi-part message in MIME format.' . "\r\n";
    $msg .= '--' . $b . "\r\n";
    $msg .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n\r\n";
    $msg .= $plainBody . "\r\n\r\n";
    $msg .= '--' . $b . "\r\n";
    $msg .= 'Content-Type: text/html; charset=UTF-8' . "\r\n\r\n";
    $msg .= $htmlBody . "\r\n\r\n";
    $msg .= '--' . $b . "--\r\n";

    // @ suppresses SMTP warnings so the JSON responses stay clean.
    return @mail(trim($to), trim($subject), $msg, $headers);
}

/**
 * Session-based rate limiter. Returns true when the visitor is submitting
 * the given form too often; callers should reject with a 429 response.
 */
function rate_limited(string $form, int $cooldownSeconds = 120): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $key = 'rl_' . md5($form);
    $now = time();
    if (isset($_SESSION[$key]) && ($now - (int)$_SESSION[$key]) < $cooldownSeconds) {
        return true;
    }
    $_SESSION[$key] = $now;
    return false;
}

/**
 * Appends one row to a CSV, guaranteeing a newline before the row so a
 * hand-edited / truncated file can never have rows glued to the previous line.
 * @return bool true if the row was written.
 */
function csv_append_row(string $csvPath, array $row): bool
{
    $needNewline = false;
    $size = @filesize($csvPath);
    if ($size !== false && $size > 0) {
        $rfp = @fopen($csvPath, 'rb');
        if ($rfp !== false) {
            fseek($rfp, -1, SEEK_END);
            $last = fgetc($rfp);
            fclose($rfp);
            if ($last !== "\n" && $last !== "\r") {
                $needNewline = true;
            }
        }
    }

    $fp = @fopen($csvPath, 'ab');
    if ($fp === false) {
        return false;
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }
    if ($needNewline) {
        fwrite($fp, "\n");
    }
    fputcsv($fp, $row, ',', '"', '"');
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}