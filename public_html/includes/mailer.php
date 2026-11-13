<?php
/**
 * Outgoing email.
 *
 * Uses PHP's mail(), which works on Hostinger shared hosting once a mailbox
 * exists for the "from" address (hPanel → Emails). For better deliverability
 * you can later switch to SMTP (e.g. PHPMailer with your Hostinger mailbox) —
 * only send_mail() needs to change.
 *
 * In the 'local' environment nothing is sent: the message (including reset
 * links) is written to storage/logs/app.log so flows can be tested.
 */

declare(strict_types=1);

function send_mail(string $to, string $subject, string $text, string $html): bool
{
    // Header-injection guard: no line breaks in addresses or subject.
    if (preg_match('/[\r\n]/', $to . $subject) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (config('app.env') === 'local') {
        app_log('info', 'Email (local: not sent)', ['to' => $to, 'subject' => $subject, 'text' => $text]);
        return true;
    }

    $fromEmail = (string) config('mail.from_email', 'no-reply@localhost');
    $fromName  = preg_replace('/[\r\n"]/', '', (string) config('mail.from_name', 'PDFNova'));
    $boundary  = 'b_' . random_token(12);

    $headers = implode("\r\n", [
        'From: "' . $fromName . '" <' . $fromEmail . '>',
        'Reply-To: ' . (string) setting('contact_email', (string) config('contact.email', $fromEmail)),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: PDFNova',
    ]);
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$boundary--";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = @mail($to, $encodedSubject, $body, $headers, '-f' . $fromEmail);
    if (!$ok) {
        app_log('error', 'mail() failed', ['to_domain' => substr(strrchr($to, '@') ?: '', 1)]);
    }
    return $ok;
}

function send_password_reset_email(string $email, string $name, string $link): bool
{
    $site = (string) config('app.name', 'PDFNova');
    $minutes = RESET_TTL_MINUTES;
    $text = "Hi $name,\n\nSomeone (hopefully you) asked to reset the password for your $site account.\n\n"
        . "Choose a new password here (valid for $minutes minutes, one use only):\n$link\n\n"
        . "If you didn't ask for this, ignore this email — your password won't change.\n\n— $site";
    ob_start();
    partial('emails/password-reset', ['name' => $name, 'link' => $link, 'minutes' => $minutes, 'site' => $site]);
    $html = (string) ob_get_clean();
    return send_mail($email, "Reset your $site password", $text, $html);
}
