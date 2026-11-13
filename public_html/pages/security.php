<?php
/**
 * /security — how files and accounts are protected. Every statement here
 * describes something the code actually does; nothing is overstated.
 */

declare(strict_types=1);

$uploadMin = (int) config('retention.upload_minutes', 60);
$resultMin = (int) config('retention.result_minutes', 120);
$processors = legal_processors();

set_page([
    'title'       => 'Security – How PDFNova protects your files | PDFNova',
    'description' => 'How PDFNova handles uploaded files, deletes them automatically, and protects accounts.',
]);
?>
<section class="page-hero">
    <div class="container narrow">
        <p class="eyebrow"><?= icon('shield', 'icon icon-sm') ?> Security</p>
        <h1 class="mt-4">How we handle your files</h1>
        <p class="lead">A plain description of what happens to a file you upload — and what we do to protect your account.</p>
    </div>
</section>

<section class="legal">
<div class="container">
<div class="prose">

<h2>Your files</h2>
<ul>
    <li><strong>Encrypted in transit.</strong> The site is served over HTTPS; plain HTTP is redirected, and browsers are told to use HTTPS only (HSTS).</li>
    <li><strong>Checked on arrival.</strong> Every upload is verified by its actual content, not just its name or extension; anything that isn't a genuine PDF, image or Office file is rejected.</li>
    <li><strong>Stored privately.</strong> Files are saved under random names in folders that cannot be opened from the web, and where scripts can't run.</li>
    <li><strong>Only you can download results.</strong> Download links are signed, expire, and only work in the browser session or account that created the file.</li>
    <li><strong>Deleted automatically.</strong> Uploads are deleted as soon as processing succeeds (at the latest after <?= e(legal_minutes($uploadMin)) ?>), results after <?= e(legal_minutes($resultMin)) ?>. A cleanup job runs every 15 minutes.</li>
    <li><strong>Some tools never upload at all.</strong> PDF to JPG and page previews run entirely in your browser.</li>
    <?php if ($processors): ?>
    <li><strong>External processing.</strong> <?= e(implode(', ', array_map(static fn($p) => $p[0] . ' (' . $p[1] . ')', $processors))) ?> — for these tools your file is sent to that provider.</li>
    <?php else: ?>
    <li><strong>No third parties.</strong> All enabled tools run on our server or in your browser — files aren't sent to other services.</li>
    <?php endif; ?>
</ul>
<p>What we don't claim: files are not separately encrypted while they sit on our server for those few minutes — they are protected by access controls and short retention instead. Passwords you use in Protect PDF or Unlock PDF are used only in memory and never stored.</p>

<h2>Your account</h2>
<ul>
    <li>Passwords are stored only as strong one-way hashes (Argon2id or bcrypt).</li>
    <li>Repeated failed sign-ins are rate-limited, and sign-in errors don't reveal whether an email is registered.</li>
    <li>Sessions use secure, HttpOnly cookies; the session is renewed on sign-in, and you can sign out other devices from Settings. Changing or resetting your password signs out your other devices.</li>
    <li>Password-reset links work once and expire after 60 minutes.</li>
</ul>

<h2>The website</h2>
<ul>
    <li>Protection against cross-site request forgery on every form and action, and against cross-site scripting with output escaping and a strict Content Security Policy.</li>
    <li>Database access uses prepared statements only.</li>
    <li>No third-party scripts, trackers or advertising on any page.</li>
</ul>

<h2>Reporting a vulnerability</h2>
<p>If you believe you've found a security issue, please email <?= legal_contact_email() ?> with the details. Please don't access other people's data, disrupt the service or publish the issue before we've had a reasonable chance to fix it. We'll reply and keep you updated.</p>

</div>
</div>
</section>
