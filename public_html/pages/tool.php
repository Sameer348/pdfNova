<?php
/**
 * ONE template for every tool page (/merge-pdf, /split-pdf, ...).
 * Everything tool-specific comes from the registry (includes/tools.php) and
 * an optional options panel in templates/tool-options/<slug>.php.
 *
 * Variables in scope: $route (from index.php) with $route['tool'].
 */

declare(strict_types=1);

$tool    = tool((string) $route['tool']);
$status  = tool_status($tool);
$ready   = tool_ready($tool);
$limits  = visitor_limits();
$off     = $status['state'] === 'unavailable';
$optionsTemplate = 'tool-options/' . $tool['slug'];
$hasOptions = is_file(BASE_PATH . '/templates/' . $optionsTemplate . '.php');
$category = tool_categories()[$tool['category']]['label'];

$related = array_filter(
    tools_by_category()[$tool['category']] ?? [],
    static fn(array $t): bool => $t['slug'] !== $tool['slug']
);
if (count($related) < 3) {
    $related += array_slice(array_filter(popular_tools(), static fn(array $t): bool => $t['slug'] !== $tool['slug']), 0, 3 - count($related), true);
}
$related = array_slice($related, 0, 3, true);

$scripts = ['js/tools.js'];
// Optional tool-specific behaviour (options logic, summaries, browser-side processing).
if (is_file(BASE_PATH . '/assets/js/tools/' . $tool['slug'] . '.js')) {
    $scripts[] = 'js/tools/' . $tool['slug'] . '.js';
}

set_page([
    'title'       => $tool['title'],
    'description' => $tool['description'],
    'body_class'  => 'page-tool',
    'scripts'     => $off ? [] : $scripts,
    'schema'      => [
        '@context' => 'https://schema.org',
        '@graph'   => array_values(array_filter([
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => absolute_url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $tool['name'], 'item' => absolute_url($tool['slug'])],
                ],
            ],
            // Only describe tools that actually work as an application.
            $off ? null : [
                '@type'               => 'WebApplication',
                'name'                => 'PDFNova ' . $tool['name'],
                'url'                 => absolute_url($tool['slug']),
                'description'         => $tool['description'],
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem'     => 'Any (web browser)',
                'offers'              => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            ],
        ])),
    ],
]);
?>
<section class="tool-hero">
    <div class="container narrow">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="<?= e(url('/')) ?>">Home</a> <?= icon('chevron-right', 'icon icon-sm') ?>
            <span><?= e($category) ?></span>
        </nav>
        <span class="tool-icon tool-icon-lg <?= e(tool_tone($tool)) ?>"><?= icon($tool['icon']) ?></span>
        <h1><?= e($tool['heading']) ?></h1>
        <p class="lead"><?= e($tool['subtitle']) ?></p>
    </div>
</section>

<section class="tool-body">
    <div class="container mid">
        <?php if ($off): ?>
            <div class="alert alert-warning tool-unavailable" role="status">
                <?= icon('info') ?>
                <div>
                    <p><strong><?= e($tool['name']) ?> isn't available yet</strong><?= e($status['note']) ?></p>
                    <p>We don't offer a version that pretends to work. Try one of the related tools below in the meantime.</p>
                </div>
            </div>
        <?php else: ?>
            <?php if ($status['state'] === 'limited' && $status['note'] !== ''): ?>
            <div class="alert alert-info mb-6" role="status"><?= icon('info') ?><p><?= e($status['note']) ?></p></div>
            <?php endif; ?>

            <div class="tool-app"
                 data-tool-app
                 data-tool="<?= e($tool['slug']) ?>"
                 data-action="<?= e($tool['action']) ?>"
                 data-upload="<?= $tool['upload'] ? '1' : '0' ?>"
                 data-upload-url="<?= e(url('api/upload.php')) ?>"
                 data-delete-url="<?= e(url('api/delete.php')) ?>"
                 data-endpoint="<?= e($tool['endpoint'] !== null ? url('api/' . $tool['endpoint'] . '.php') : '') ?>"
                 data-ready="<?= $ready ? '1' : '0' ?>"
                 data-exts="<?= e(implode(',', $tool['accept'])) ?>"
                 data-multiple="<?= $tool['multiple'] ? '1' : '0' ?>"
                 data-min-files="<?= (int) $tool['min_files'] ?>"
                 data-max-files="<?= $tool['multiple'] ? (int) $limits['max_files'] : 1 ?>"
                 data-max-bytes="<?= (int) $limits['max_file_bytes'] ?>">

                <!-- Stage 1: choose files -->
                <div data-stage="pick">
                    <?php partial('components/uploader', ['tool' => $tool, 'limits' => $limits]); ?>
                </div>

                <!-- Stage 2: review files + options -->
                <div class="workspace" data-stage="work" hidden>
                    <div class="workspace-files">
                        <div class="workspace-head">
                            <h2 class="h4"><?= $tool['multiple'] ? 'Your files' : 'Your file' ?> <span class="badge" data-file-count>0</span></h2>
                            <?php if ($tool['multiple']): ?>
                            <button class="btn btn-secondary btn-sm" type="button" data-browse><?= icon('plus', 'icon icon-sm') ?> Add files</button>
                            <?php endif; ?>
                        </div>
                        <?php if ($tool['multiple']): ?>
                        <p class="hint workspace-hint"><?= icon('grip', 'icon icon-sm') ?> Drag files, or use the arrows, to change the order.</p>
                        <?php endif; ?>
                        <ul class="file-list" data-file-list aria-live="polite"></ul>
                        <!-- Optional visual area used by some tools (e.g. page thumbnails) -->
                        <div class="tool-canvas" data-tool-canvas hidden></div>
                    </div>

                    <aside class="workspace-side">
                        <?php if ($hasOptions): ?>
                        <form class="form tool-options" data-tool-options novalidate>
                            <?php partial($optionsTemplate, ['tool' => $tool, 'limits' => $limits]); ?>
                        </form>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-lg btn-block" type="button" data-run disabled>
                            <?= e($tool['action']) ?> <?= icon('arrow-right') ?>
                        </button>
                        <p class="hint run-hint" data-run-hint>
                            <?php if (!$ready): ?>Processing for this tool is not switched on yet.<?php endif; ?>
                        </p>
                    </aside>
                </div>

                <!-- Stage 3: processing -->
                <div class="stage-card" data-stage="processing" hidden>
                    <div class="spinner spinner-lg" aria-hidden="true"></div>
                    <h2 class="h4" data-processing-title>Processing…</h2>
                    <p class="muted small" data-processing-text>This usually takes a few seconds. Please keep this page open.</p>
                    <div data-processing-progress></div>
                </div>

                <!-- Stage 4: result -->
                <div data-stage="result" hidden></div>

                <!-- Errors for the whole tool (per-file errors appear in the list) -->
                <div class="tool-errors" data-tool-errors></div>
            </div>

            <ul class="tool-facts">
                <li><?= icon('shield', 'icon icon-sm') ?> Files are deleted automatically within <?= (int) ceil((int) config('retention.result_minutes', 120) / 60) ?> hours</li>
                <li><?= icon('lock', 'icon icon-sm') ?> Private download link</li>
                <li><?= icon('user', 'icon icon-sm') ?> <?= $limits['is_guest'] ? 'No account needed' : 'Plan: ' . e(ucfirst($limits['plan'])) ?></li>
            </ul>
        <?php endif; ?>
    </div>
</section>

<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <h2 class="h3">How to use <?= e($tool['name']) ?></h2>
        </div>
        <ol class="steps steps-compact">
            <li class="step"><span class="step-num">1</span><h3>Add your <?= $tool['multiple'] ? 'files' : 'file' ?></h3>
                <p>Drop <?= e(strtoupper(implode(', ', array_unique(array_map(static fn($e) => $e === 'jpeg' ? 'jpg' : $e, $tool['accept']))))) ?> <?= $tool['multiple'] ? 'files' : 'a file' ?> above or browse your device.</p></li>
            <li class="step"><span class="step-num">2</span><h3><?= $hasOptions ? 'Choose your options' : 'Check your files' ?></h3>
                <p><?= $tool['multiple'] ? 'Put the files in the order you want' : 'Make sure you picked the right file' ?><?= $hasOptions ? ' and adjust the settings.' : '.' ?></p></li>
            <li class="step"><span class="step-num">3</span><h3><?= e($tool['action']) ?></h3>
                <p>Press the button and download your result from the private link.</p></li>
        </ol>

        <div class="related">
            <h2 class="h4">Related tools</h2>
            <div class="grid grid-3">
                <?php foreach ($related as $slug => $t) { partial('components/tool-card', ['slug' => $slug, 'tool' => $t]); } ?>
            </div>
        </div>
    </div>
</section>
