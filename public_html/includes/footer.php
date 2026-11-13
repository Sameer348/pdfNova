<?php
/**
 * Global layout — footer, cookie notice, toast region.
 */

declare(strict_types=1);

$grouped    = tools_by_category();
$categories = tool_categories();
$footerCols = [
    'organize'     => $grouped['organize'] + $grouped['edit'],
    'optimize'     => $grouped['optimize'] + $grouped['security'],
    'convert-to'   => $grouped['convert-to'],
    'convert-from' => $grouped['convert-from'],
];
$footerLabels = [
    'organize'     => 'Organize & Edit',
    'optimize'     => 'Optimize & Secure',
    'convert-to'   => $categories['convert-to']['label'],
    'convert-from' => $categories['convert-from']['label'],
];
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="brand" href="<?= e(url('/')) ?>" aria-label="PDFNova home"><?= logo('logo logo-light') ?></a>
                <p>Simple, fast PDF tools in your browser. Files you upload are processed and then deleted automatically.</p>
                <a class="footer-security" href="<?= e(url('security')) ?>"><?= icon('shield', 'icon icon-sm') ?> How we handle your files</a>
            </div>

            <?php foreach ($footerCols as $key => $list): ?>
            <nav class="footer-col" aria-label="<?= e($footerLabels[$key]) ?>">
                <p class="footer-title"><?= e($footerLabels[$key]) ?></p>
                <ul>
                    <?php foreach ($list as $slug => $t): ?>
                    <li><a href="<?= e(url($slug)) ?>"><?= e($t['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <?php endforeach; ?>

            <nav class="footer-col" aria-label="Company">
                <p class="footer-title">Company</p>
                <ul>
                    <li><a href="<?= e(url('pricing')) ?>">Pricing</a></li>
                    <li><a href="<?= e(url('security')) ?>">Security</a></li>
                    <li><a href="<?= e(url('contact')) ?>">Contact</a></li>
                    <li><a href="<?= e(url('privacy-policy')) ?>">Privacy Policy</a></li>
                    <li><a href="<?= e(url('terms')) ?>">Terms of Service</a></li>
                    <li><a href="<?= e(url('cookies')) ?>">Cookie Notice</a></li>
                </ul>
            </nav>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= gmdate('Y') ?> <?= e(config('app.name', 'PDFNova')) ?>. All rights reserved.</p>
            <p class="footer-note">Uploaded files are deleted automatically within <?= (int) config('retention.result_minutes', 120) / 60 ?> hours.</p>
        </div>
    </div>
</footer>

<!-- Cookie notice: informational only — PDFNova sets essential cookies only. -->
<div class="cookie-notice" data-cookie-notice role="region" aria-label="Cookie notice">
    <p><?= icon('info', 'icon icon-sm') ?> We only use essential cookies to keep you signed in and protect forms. No advertising or tracking cookies.
        <a href="<?= e(url('cookies')) ?>">Learn more</a></p>
    <button class="btn btn-secondary btn-sm" type="button" data-cookie-dismiss>Got it</button>
</div>

<div class="toast-region" data-toast-region aria-live="polite" aria-atomic="false"></div>
</body>
</html>
