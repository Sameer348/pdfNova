<?php
/**
 * Password reset email (HTML part). Inline styles are required for email
 * clients; this file is never served as a web page.
 * Vars: string $name, string $link, int $minutes, string $site
 */

declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;">
    <tr><td style="padding:28px 28px 8px;font-size:22px;font-weight:bold;">PDF<span style="color:#d21f45;">Nova</span></td></tr>
    <tr><td style="padding:8px 28px;font-size:15px;line-height:1.6;">
      <p>Hi <?= e($name) ?>,</p>
      <p>Someone (hopefully you) asked to reset the password for your <?= e($site) ?> account.</p>
      <p style="margin:24px 0;"><a href="<?= e($link) ?>" style="background:#d21f45;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold;display:inline-block;">Choose a new password</a></p>
      <p style="font-size:13px;color:#475569;">This link works once and expires in <?= (int) $minutes ?> minutes. If the button doesn't work, copy this address into your browser:<br><span style="word-break:break-all;"><?= e($link) ?></span></p>
      <p style="font-size:13px;color:#475569;">If you didn't ask for this, you can ignore this email — your password won't change.</p>
    </td></tr>
    <tr><td style="padding:16px 28px 28px;font-size:12px;color:#94a3b8;">— <?= e($site) ?></td></tr>
  </table>
</body>
</html>
