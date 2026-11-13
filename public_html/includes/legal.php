<?php
/**
 * Helpers for the legal pages. Values come from config so the text always
 * matches the actual deployment (retention times, enabled processors).
 */

declare(strict_types=1);

const LEGAL_UPDATED = '2026-11-11';

/** Config value, or a highlighted placeholder when it hasn't been filled in. */
function legal_value(string $key, string $placeholder): string
{
    $value = trim((string) config('legal.' . $key, ''));
    return $value !== '' ? e($value) : '<span class="placeholder">[' . e($placeholder) . ']</span>';
}

function legal_contact_email(): string
{
    $email = (string) setting('contact_email', (string) config('contact.email'));
    return '<a href="mailto:' . e($email) . '">' . e($email) . '</a>';
}

/** Human-friendly duration from minutes. */
function legal_minutes(int $minutes): string
{
    return $minutes % 60 === 0 ? ($minutes / 60) . ' ' . ($minutes === 60 ? 'hour' : 'hours') : $minutes . ' minutes';
}

/** External processors that are actually enabled in this deployment. */
function legal_processors(): array
{
    $list = [];
    if (external_api_configured('converter')) {
        $list[] = ['CloudConvert', 'converting Office documents to and from PDF', 'https://cloudconvert.com/privacy'];
    }
    if (external_api_configured('ocr')) {
        $list[] = ['OCR.space', 'recognising text in scanned PDFs (OCR)', 'https://ocr.space/privacypolicy'];
    }
    if (config('payment.provider', 'none') === 'stripe') {
        $list[] = ['Stripe', 'processing payments', 'https://stripe.com/privacy'];
    }
    return $list;
}

function legal_head(string $title, string $intro): void
{
    ?>
    <section class="page-hero">
        <div class="container narrow">
            <p class="eyebrow">Legal</p>
            <h1 class="mt-4"><?= e($title) ?></h1>
            <p class="lead"><?= e($intro) ?></p>
            <p class="legal-updated">Last updated: <?= e(date('F j, Y', strtotime(LEGAL_UPDATED))) ?></p>
        </div>
    </section>
    <?php if (trim((string) config('legal.company', '')) === '' && config('app.env') !== 'production'): ?>
    <div class="container"><div class="alert alert-warning legal-note"><?= icon('info') ?><p><strong>Before going live</strong>Fill in <code>legal.company</code>, <code>legal.address</code> and <code>legal.country</code> in config.php and have these texts reviewed for your country. They describe how PDFNova actually works, but they are not legal advice.</p></div></div>
    <?php endif;
}
