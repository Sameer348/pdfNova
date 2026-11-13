<?php
/**
 * /privacy-policy — generated from the real configuration so retention
 * periods and processors always match the deployment.
 */

declare(strict_types=1);

$uploadMin  = (int) config('retention.upload_minutes', 60);
$resultMin  = (int) config('retention.result_minutes', 120);
$historyMax = (int) config('retention.history_days', 90);
$guestDays  = (int) config('retention.guest_history_days', 30);
$logDays    = (int) config('retention.log_days', 365);
$plans      = db_all('SELECT name, history_days FROM plans WHERE is_active = 1 ORDER BY sort_order');
$processors = legal_processors();

set_page([
    'title'       => 'Privacy Policy | PDFNova',
    'description' => 'What PDFNova collects, why, how long it is kept and how files are deleted.',
]);
legal_head('Privacy Policy', 'What we collect, why, how long we keep it — and how your files are deleted.');
?>
<section class="legal">
<div class="container">
<div class="prose">

<h2>The short version</h2>
<ul>
    <li>Files you upload are used only to run the tool you chose, and are <strong>deleted automatically</strong>: uploads right after successful processing (and at the latest after <?= e(legal_minutes($uploadMin)) ?>), results after <?= e(legal_minutes($resultMin)) ?>.</li>
    <li>We don't sell data, show ads, or use analytics or tracking cookies.</li>
    <li>You can use most tools without an account. If you create one, you can delete it — and everything linked to it — at any time.</li>
</ul>

<h2>1. Who we are</h2>
<p>This service is operated by <?= legal_value('company', 'Company name') ?>, <?= legal_value('address', 'Postal address') ?> (“PDFNova”, “we”). Contact us about privacy at <?= legal_contact_email() ?>.</p>

<h2>2. What we collect</h2>
<ul>
    <li><strong>Files you upload</strong> to a tool, stored temporarily under random names in a private folder that cannot be accessed from the web.</li>
    <li><strong>Task records</strong>: the tool used, file names, sizes, page counts, the result's status and the time. Signed-in users see these in their dashboard.</li>
    <li><strong>Account details</strong> if you register: name, email address and a one-way password hash (we never store your password itself). We also record when you last signed in.</li>
    <li><strong>Technical data</strong>: your IP address and your browser's user-agent string, used for security, abuse prevention, usage limits and the list of signed-in devices in your account.</li>
    <li><strong>Messages</strong> you send through the contact form.</li>
</ul>
<p>Passwords you type into Protect PDF or Unlock PDF are used only in memory to encrypt or decrypt your file. They are never stored or logged.</p>

<h2>3. Tools that run in your browser</h2>
<p>PDF to JPG, page previews and the watermark preview are created on your own device; those files are not uploaded. If you pick a file on the homepage first, it is kept in your browser's storage (IndexedDB) for up to 10 minutes so the tool page can open it, and removed as soon as it is used.</p>

<h2>4. How long we keep things</h2>
<table class="table">
    <thead><tr><th>Data</th><th>Kept for</th></tr></thead>
    <tbody>
        <tr><td>Uploaded files</td><td>Until processing succeeds; at most <?= e(legal_minutes($uploadMin)) ?></td></tr>
        <tr><td>Result files</td><td><?= e(legal_minutes($resultMin)) ?></td></tr>
        <tr><td>Task records (signed in)</td><td>Shown for <?= e(implode(', ', array_map(static fn($p) => $p['name'] . ' ' . (int) $p['history_days'] . ' days', $plans))) ?>; deleted after <?= $historyMax ?> days</td></tr>
        <tr><td>Task records (guests)</td><td><?= $guestDays ?> days</td></tr>
        <tr><td>Activity and security logs</td><td><?= $logDays ?> days</td></tr>
        <tr><td>Signed-in device records</td><td>30 days after the last activity or sign-out</td></tr>
        <tr><td>Password-reset links</td><td>Valid for 60 minutes; deleted within a day</td></tr>
        <tr><td>Account details</td><td>Until you delete your account</td></tr>
        <tr><td>Contact messages</td><td>Until handled; archived messages are deleted after <?= $logDays ?> days</td></tr>
    </tbody>
</table>
<p>Deletion runs automatically every 15 minutes. When you delete your account, your account details and any stored files are deleted immediately; task and activity records lose their link to you and are removed when their normal retention period ends.</p>

<h2>5. Why we use your data</h2>
<ul>
    <li>To provide the tools and your account (necessary to provide the service you requested).</li>
    <li>To keep the service secure and fair: rate limits, abuse prevention, usage limits (legitimate interest).</li>
    <li>To answer your messages and send password-reset emails you ask for.</li>
</ul>
<p>We do not use your files or data for advertising, profiling, or training machine-learning models.</p>

<h2>6. Who else processes data</h2>
<ul>
    <li>Our hosting provider stores the website, database and temporary files on our behalf.</li>
    <li>Emails (password resets, replies) are sent through our hosting provider's mail server.</li>
    <?php foreach ($processors as [$name, $purpose, $link]): ?>
    <li><strong><?= e($name) ?></strong> — <?= e($purpose) ?>. When you use that tool, your file is sent to <?= e($name) ?> for processing (<a href="<?= e($link) ?>" rel="noopener">their privacy policy</a>).</li>
    <?php endforeach; ?>
    <?php if (!$processors): ?>
    <li>No other services receive your files: all enabled tools run on our server or in your browser.</li>
    <?php endif; ?>
</ul>
<p>We don't sell or rent personal data. We may disclose information if required by law.</p>

<h2>7. Cookies</h2>
<p>We use one essential cookie to keep you signed in and protect forms. See the <a href="<?= e(url('cookies')) ?>">Cookie Notice</a> for details.</p>

<h2>8. Security</h2>
<p>How we protect files and accounts is described on our <a href="<?= e(url('security')) ?>">Security page</a>.</p>

<h2>9. Your rights</h2>
<p>Depending on where you live, you may have the right to access, correct, delete or export your personal data, and to object to or restrict its use. You can update or delete your account yourself on the Account page; for anything else, email <?= legal_contact_email() ?>. You can also complain to your local data-protection authority.</p>

<h2>10. Children</h2>
<p>PDFNova is not directed at children under 13, and we don't knowingly collect their data.</p>

<h2>11. Changes</h2>
<p>If we change this policy we'll update the date at the top, and for important changes we'll tell signed-in users.</p>

</div>
</div>
</section>
