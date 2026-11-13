<?php
/**
 * /terms — Terms of Service.
 */

declare(strict_types=1);

set_page([
    'title'       => 'Terms of Service | PDFNova',
    'description' => 'The rules for using PDFNova: your files, acceptable use, plans and our responsibilities.',
]);
legal_head('Terms of Service', 'The rules for using PDFNova, written to be readable.');
?>
<section class="legal">
<div class="container">
<div class="prose">

<h2>1. Agreement</h2>
<p>By using PDFNova, operated by <?= legal_value('company', 'Company name') ?> (“we”), you agree to these terms and to our <a href="<?= e(url('privacy-policy')) ?>">Privacy Policy</a>. If you don't agree, please don't use the service.</p>

<h2>2. The service</h2>
<p>PDFNova provides online tools to work with PDF files. Some tools run on our server, some in your browser, and some may depend on external services; a tool that isn't available is clearly marked. We may add, change or remove tools.</p>

<h2>3. Your files</h2>
<ul>
    <li>You keep all rights to the files you upload and the results you download.</li>
    <li>You give us permission to process your files only as needed to run the tool you chose. Files are deleted automatically as described in the Privacy Policy.</li>
    <li>Results are provided for you to check. Keep your original files: we are not a storage service and results are deleted after a short time.</li>
</ul>

<h2>4. Accounts</h2>
<p>Give accurate details, keep your password secure and don't share your account. You are responsible for activity on your account. You can delete your account at any time.</p>

<h2>5. Acceptable use</h2>
<p>You must not:</p>
<ul>
    <li>upload files you don't have the right to process, or use Unlock PDF on documents you aren't authorised to unlock;</li>
    <li>upload malware or illegal content, or use PDFNova to infringe anyone's rights;</li>
    <li>try to bypass usage limits, access other people's files, or test or break our security without permission (see the Security page for responsible disclosure);</li>
    <li>overload the service, for example with automated mass requests.</li>
</ul>
<p>We may suspend or close accounts that break these rules.</p>

<h2>6. Plans and payment</h2>
<p>The Free plan and guest use have the limits shown on the <a href="<?= e(url('pricing')) ?>">Pricing page</a>. Paid plans give the limits listed there for the period agreed. Online payment isn't available yet; paid plans are currently arranged with us directly. We may change prices for future periods with reasonable notice; features marked “coming soon” are not part of a plan until released.</p>

<h2>7. Availability</h2>
<p>We aim to keep PDFNova available and accurate, but we can't promise it will always be uninterrupted or error-free. Maintenance may make it unavailable from time to time.</p>

<h2>8. Disclaimer</h2>
<p>The service is provided “as is”. To the extent the law allows, we disclaim implied warranties such as fitness for a particular purpose. Always check results — especially conversions and compression — before relying on them.</p>

<h2>9. Limitation of liability</h2>
<p>To the extent the law allows, we are not liable for indirect or consequential losses, or for loss of data you didn't keep a copy of. Our total liability for any claim is limited to the amount you paid us in the 12 months before the claim (or a nominal amount for free use). Nothing in these terms limits liability that cannot be limited by law.</p>

<h2>10. Ending use</h2>
<p>You can stop using PDFNova and delete your account at any time. We may end or suspend access if you breach these terms or if we discontinue the service, with notice where reasonable.</p>

<h2>11. Law</h2>
<p>These terms are governed by the laws of <?= legal_value('country', 'Country') ?>, without prejudice to mandatory consumer-protection rules where you live.</p>

<h2>12. Contact</h2>
<p>Questions about these terms: <?= legal_contact_email() ?>, or <?= legal_value('address', 'Postal address') ?>.</p>

</div>
</div>
</section>
