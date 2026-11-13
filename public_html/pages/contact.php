<?php
/**
 * /contact — contact form → contact_messages (+ email notification).
 *
 * Spam protection without third-party CAPTCHAs (no external scripts, no
 * tracking): CSRF token, hidden honeypot field, a signed timestamp that
 * rejects forms submitted in under 3 seconds, and 5 messages/hour per IP.
 */

declare(strict_types=1);

$user = current_user();
$errors = [];
$sent = false;
$values = [
    'name'    => $user['name'] ?? '',
    'email'   => $user['email'] ?? '',
    'subject' => (string) ($_GET['subject'] ?? ''),
    'message' => '',
];
$values['subject'] = mb_substr(preg_replace('/[\x00-\x1F\x7F]+/u', '', $values['subject']) ?? '', 0, 150);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    foreach (array_keys($values) as $k) {
        $values[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $stamp = verify_payload((string) ($_POST['form_token'] ?? ''));
    $isBot = ($_POST['website'] ?? '') !== ''                               // honeypot filled
        || $stamp === null || time() - (int) ($stamp['t'] ?? 0) < 3;        // missing/forged token or too fast

    if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 100) {
        $errors['name'] = 'Enter your name.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 191) {
        $errors['email'] = 'Enter a valid email address so we can reply.';
    }
    if (mb_strlen($values['subject']) < 3 || mb_strlen($values['subject']) > 150) {
        $errors['subject'] = 'Enter a subject (3–150 characters).';
    }
    if (mb_strlen($values['message']) < 10 || mb_strlen($values['message']) > 5000) {
        $errors['message'] = 'Write a message of 10–5000 characters.';
    }

    if (!$errors && $isBot) {
        $sent = true; // pretend success so bots learn nothing; nothing is stored
    } elseif (!$errors && !rate_limit('contact', client_ip(), 5, 3600)) {
        $errors['form'] = 'You\'ve sent several messages already. Please try again in an hour.';
    } elseif (!$errors) {
        $subject = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $values['subject']) ?? '';
        db_insert(
            'INSERT INTO contact_messages (user_id, name, email, subject, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$user['id'] ?? null, $values['name'], mb_strtolower($values['email']), $subject, $values['message'],
             client_ip_bin(), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]
        );
        $to = (string) setting('contact_email', (string) config('contact.email'));
        send_mail($to, 'New contact message: ' . mb_substr($subject, 0, 80),
            "From: {$values['name']} <{$values['email']}>\n\n{$values['message']}\n\n— Sent from the PDFNova contact form. Reply in the admin panel or by email.",
            '<p><strong>From:</strong> ' . e($values['name']) . ' &lt;' . e($values['email']) . '&gt;</p><p style="white-space:pre-wrap">' . e($values['message']) . '</p>');
        $sent = true;
    }
}

set_page([
    'title'       => 'Contact us | PDFNova',
    'description' => 'Questions, feedback or plan upgrades — get in touch with the PDFNova team.',
]);
$formToken = sign_payload(['t' => time()], 3600);
?>
<section class="page-hero">
    <div class="container narrow">
        <p class="eyebrow">Contact</p>
        <h1 class="mt-4">How can we help?</h1>
        <p class="lead">Questions about a tool, your account or upgrading your plan — send us a message.</p>
    </div>
</section>

<section class="section pricing-section">
    <div class="container mid contact-grid">
        <div class="panel">
            <?php if ($sent): ?>
                <div class="empty-state">
                    <div class="auth-icon"><?= icon('check-circle', 'icon icon-xl') ?></div>
                    <h2 class="h3">Thanks — your message is on its way</h2>
                    <p>We usually reply within two working days to the email address you gave.</p>
                    <a class="btn btn-secondary" href="<?= e(url('/')) ?>">Back to the tools</a>
                </div>
            <?php else: ?>
                <?php if (!empty($errors['form'])): ?><div class="alert alert-error mb-4" role="alert"><?= icon('alert') ?><p><?= e($errors['form']) ?></p></div><?php endif; ?>
                <form class="form" method="post" action="<?= e(url('contact')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_token" value="<?= e($formToken) ?>">
                    <div class="hp-field" aria-hidden="true"><label for="c-website">Leave this empty</label><input id="c-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
                    <div class="form-row form-row-2">
                        <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                            <label class="label" for="c-name">Name</label>
                            <input class="input" id="c-name" name="name" type="text" autocomplete="name" maxlength="100" required value="<?= e($values['name']) ?>"<?= field_aria($errors, 'name') ?>>
                            <?= field_error($errors, 'name') ?>
                        </div>
                        <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                            <label class="label" for="c-email">Email</label>
                            <input class="input" id="c-email" name="email" type="email" autocomplete="email" maxlength="191" required value="<?= e($values['email']) ?>"<?= field_aria($errors, 'email') ?>>
                            <?= field_error($errors, 'email') ?>
                        </div>
                    </div>
                    <div class="field<?= isset($errors['subject']) ? ' has-error' : '' ?>">
                        <label class="label" for="c-subject">Subject</label>
                        <input class="input" id="c-subject" name="subject" type="text" maxlength="150" required value="<?= e($values['subject']) ?>"<?= field_aria($errors, 'subject') ?>>
                        <?= field_error($errors, 'subject') ?>
                    </div>
                    <div class="field<?= isset($errors['message']) ? ' has-error' : '' ?>">
                        <label class="label" for="c-message">Message</label>
                        <textarea class="textarea" id="c-message" name="message" maxlength="5000" rows="6" required<?= field_aria($errors, 'message') ?>><?= e($values['message']) ?></textarea>
                        <?= field_error($errors, 'message') ?>
                    </div>
                    <p class="hint">Please don't send passwords or confidential documents by message. We use your details only to reply — see our <a href="<?= e(url('privacy-policy')) ?>">Privacy Policy</a>.</p>
                    <div><button class="btn btn-primary btn-lg" type="submit">Send message</button></div>
                </form>
            <?php endif; ?>
        </div>
        <aside class="stack">
            <div class="panel"><h2 class="h4">Upgrading your plan?</h2><p class="small muted mt-2">Online payment isn't available yet. Tell us which plan you'd like and the email of your account, and we'll set it up.</p></div>
            <div class="panel"><h2 class="h4">Email</h2><p class="small mt-2"><a href="mailto:<?= e((string) setting('contact_email', (string) config('contact.email'))) ?>"><?= e((string) setting('contact_email', (string) config('contact.email'))) ?></a></p></div>
            <div class="panel"><h2 class="h4">Security issue?</h2><p class="small muted mt-2">See our <a href="<?= e(url('security')) ?>">Security page</a> for how to report it responsibly.</p></div>
        </aside>
    </div>
</section>
