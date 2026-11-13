<?php
/**
 * 404 — page not found. index.php sets the HTTP status before including this.
 */

declare(strict_types=1);

set_page([
    'title'       => 'Page not found | PDFNova',
    'description' => 'The page you are looking for does not exist.',
    'robots'      => 'noindex,follow',
]);
?>
<section class="error-page">
    <div class="container narrow">
        <p class="error-code">404</p>
        <h1 class="h2">We couldn't find that page</h1>
        <p class="lead">The link may be broken or the page may have moved. Try one of our popular tools instead.</p>
        <div class="row">
            <a class="btn btn-primary" href="<?= e(url('/')) ?>">Back to home</a>
            <a class="btn btn-secondary" href="<?= e(url('merge-pdf')) ?>">Merge PDF</a>
            <a class="btn btn-secondary" href="<?= e(url('compress-pdf')) ?>">Compress PDF</a>
        </div>
    </div>
</section>
