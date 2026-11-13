<?php
/**
 * /cookies — Cookie Notice. Lists exactly what the site stores in the browser.
 */

declare(strict_types=1);

set_page([
    'title'       => 'Cookie Notice | PDFNova',
    'description' => 'PDFNova uses one essential cookie and no tracking or advertising cookies.',
]);
legal_head('Cookie Notice', 'One essential cookie. No tracking, no advertising, no analytics.');
?>
<section class="legal">
<div class="container">
<div class="prose">

<h2>What we store in your browser</h2>
<table class="table table-stack">
    <thead><tr><th>Name</th><th>Type</th><th>Purpose</th><th>Duration</th></tr></thead>
    <tbody>
        <tr><td data-label="Name"><code><?= e((string) config('session.name', 'pdfnova_sid')) ?></code></td><td data-label="Type">Essential cookie</td><td data-label="Purpose">Keeps your session: sign-in, your temporary files, and protection against forged form submissions. HttpOnly, sent only over HTTPS, not shared with other sites.</td><td data-label="Duration">Until you close the browser; ends after 2 hours of inactivity (24 hours at most)</td></tr>
        <tr><td data-label="Name"><code>pdfnova_cookie_notice</code></td><td data-label="Type">Local storage</td><td data-label="Purpose">Remembers that you closed the cookie notice.</td><td data-label="Duration">Until you clear site data</td></tr>
        <tr><td data-label="Name"><code>pdfnova</code></td><td data-label="Type">IndexedDB</td><td data-label="Purpose">Hands a file you picked on the homepage to the tool page. Nothing is uploaded until you run a tool.</td><td data-label="Duration">Deleted when used, at most 10 minutes</td></tr>
    </tbody>
</table>

<h2>What we don't use</h2>
<p>No advertising cookies, no analytics or tracking cookies, no social-media widgets and no third-party scripts. Because we only use storage that is strictly necessary for the service you ask for, we don't need to ask for consent — the notice at the bottom of the page is for information.</p>

<h2>Controlling cookies</h2>
<p>You can delete or block cookies in your browser settings. If you block the essential cookie, you won't be able to sign in or use tools that upload files.</p>

<p>Questions? Email <?= legal_contact_email() ?>.</p>

</div>
</div>
</section>
