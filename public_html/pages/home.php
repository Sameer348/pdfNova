<?php
/**
 * Homepage.
 *
 * Sections: hero with quick-start drop zone · popular tools · more tools ·
 * why PDFNova · how it works · FAQ · CTA.
 *
 * The quick-start drop zone (assets/js/home.js) lets a visitor pick a file
 * first, shows only the tools that accept that file type, and hands the file
 * to the chosen tool page in the browser (IndexedDB) — nothing is uploaded
 * until the visitor runs a tool.
 */

declare(strict_types=1);

$retentionHours = max(1, (int) round((int) config('retention.result_minutes', 120) / 60));
$guestMaxMb     = 10; // shown as guidance; the real limit is enforced server-side per plan

$faq = [
    [
        'q' => 'Is PDFNova free to use?',
        'a' => 'Yes. The Free plan lets you use the core tools with daily limits and a smaller maximum file size. Paid plans raise those limits.',
    ],
    [
        'q' => 'What happens to my files?',
        'a' => "Uploaded files are stored temporarily under random names, used only to run the tool you chose, and deleted automatically — input files right after processing and results within {$retentionHours} hours.",
    ],
    [
        'q' => 'Do I need to install anything?',
        'a' => 'No. PDFNova works in any modern browser on desktop, tablet or phone.',
    ],
    [
        'q' => 'Do I need an account?',
        'a' => 'No account is needed for basic use. A free account adds a history of your recent jobs and higher limits.',
    ],
];

set_page([
    'title'       => 'PDFNova – Powerful PDF tools, made simple',
    'description' => 'Merge, split, compress, convert and manage your PDF files quickly and securely. Free online PDF tools that work on any device.',
    'body_class'  => 'page-home',
    'scripts'     => ['js/home.js'],
    'schema'      => [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type' => 'WebSite',
                'name'  => 'PDFNova',
                'url'   => absolute_url('/'),
            ],
            [
                '@type' => 'Organization',
                'name'  => 'PDFNova',
                'url'   => absolute_url('/'),
                'logo'  => absolute_url('assets/images/logo.svg'),
            ],
            [
                '@type'      => 'FAQPage',
                'mainEntity' => array_map(static fn(array $f): array => [
                    '@type'          => 'Question',
                    'name'           => $f['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
                ], $faq),
            ],
        ],
    ],
]);

?>

<!-- ===================================================================== -->
<!-- Hero                                                                   -->
<!-- ===================================================================== -->
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <p class="eyebrow"><?= icon('sparkles', 'icon icon-sm') ?> Free online PDF toolkit</p>
            <h1>Powerful PDF tools, <span class="text-accent">made simple.</span></h1>
            <p class="lead">Merge, split, compress, convert and manage your PDF files quickly and securely.</p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="#tools"><?= icon('organize') ?> Explore PDF Tools</a>
                <button class="btn btn-secondary btn-lg" type="button" data-quickstart-browse><?= icon('upload') ?> Upload a PDF</button>
            </div>
            <ul class="hero-trust">
                <li><?= icon('check', 'icon icon-sm') ?> No installation</li>
                <li><?= icon('check', 'icon icon-sm') ?> Works on any device</li>
                <li><?= icon('check', 'icon icon-sm') ?> Files deleted automatically</li>
            </ul>
        </div>

        <!-- Quick start: choose a file, then a tool -->
        <div class="hero-panel" data-quickstart data-max-bytes="<?= (int) config('uploads.hard_max_mb', 200) * 1024 * 1024 ?>">
            <div class="quick-drop" data-quickstart-drop>
                <div class="quick-art" aria-hidden="true">
                    <span class="qa-page qa-page-1"></span>
                    <span class="qa-page qa-page-2"></span>
                    <span class="qa-page qa-page-3"><?= icon('file', 'icon icon-xl') ?></span>
                </div>
                <h2 class="h4">Start with your file</h2>
                <p class="muted small">Drop a PDF, image or Office document here and we'll show the tools that work with it.</p>
                <button class="btn btn-primary" type="button" data-quickstart-browse><?= icon('upload', 'icon icon-sm') ?> Choose file</button>
                <p class="hint">PDF, JPG, PNG, Word, Excel, PowerPoint · Up to <?= (int) $guestMaxMb ?> MB per file without an account</p>
                <input class="visually-hidden" type="file" multiple tabindex="-1" aria-hidden="true"
                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.ppt,.pptx" data-quickstart-input>
            </div>

            <!-- Shown after a file is chosen -->
            <div class="quick-choose" data-quickstart-choose hidden>
                <div class="quick-file">
                    <span class="tool-icon tool-icon-sm tone-red"><?= icon('file') ?></span>
                    <div class="quick-file-meta">
                        <p class="quick-file-name" data-quickstart-name></p>
                        <p class="hint" data-quickstart-size></p>
                    </div>
                    <button class="icon-btn" type="button" data-quickstart-reset aria-label="Choose a different file"><?= icon('close', 'icon icon-sm') ?></button>
                </div>
                <p class="label">What would you like to do?</p>
                <ul class="quick-tools" data-quickstart-tools>
                    <?php foreach (tools() as $slug => $t): $available = tool_available($t); ?>
                    <li<?= $available ? '' : ' class="is-off"' ?> data-accept="<?= e(implode(',', $t['accept'])) ?>" data-multiple="<?= $t['multiple'] ? '1' : '0' ?>" hidden>
                        <button class="menu-link" type="button" data-quickstart-tool="<?= e($slug) ?>"
                                data-href="<?= e(url($slug)) ?>"<?= $available ? '' : ' disabled' ?>>
                            <span class="tool-icon tool-icon-sm <?= e(tool_tone($t)) ?>"><?= icon($t['icon']) ?></span>
                            <span class="quick-tool-name"><?= e($t['name']) ?></span>
                            <?php if (!$available): ?><span class="badge">Coming soon</span><?php endif; ?>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p class="hint">Your file stays in your browser until you run a tool.</p>
            </div>

            <div class="alert alert-error" data-quickstart-error role="alert" hidden>
                <?= icon('alert') ?><p></p>
            </div>
        </div>
    </div>
</section>

<!-- ===================================================================== -->
<!-- Popular tools                                                          -->
<!-- ===================================================================== -->
<section class="section section-soft" id="tools">
    <div class="container">
        <div class="section-head">
            <p class="eyebrow">Popular tools</p>
            <h2>Everything you need to work with PDFs</h2>
            <p>Pick a tool to get started. No sign-up required for basic use.</p>
        </div>

        <div class="grid grid-tools">
            <?php foreach (popular_tools() as $slug => $t) { partial('components/tool-card', ['slug' => $slug, 'tool' => $t]); } ?>
        </div>

        <div class="more-tools">
            <p class="label">More tools</p>
            <ul class="chip-list">
                <?php foreach (other_tools() as $slug => $t): ?>
                <li><a class="chip" href="<?= e(url($slug)) ?>">
                    <span class="tool-icon tool-icon-sm <?= e(tool_tone($t)) ?>"><?= icon($t['icon']) ?></span>
                    <?= e($t['name']) ?>
                </a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<!-- ===================================================================== -->
<!-- Why PDFNova                                                            -->
<!-- ===================================================================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <p class="eyebrow">Why PDFNova?</p>
            <h2>Simple tools that respect your files</h2>
            <p>Built to do one job well: get your document sorted and get out of your way.</p>
        </div>

        <div class="grid grid-3 features">
            <div class="feature-card">
                <span class="feature-icon"><?= icon('zap') ?></span>
                <h3>Fast processing</h3>
                <p>Merging, splitting, rotating and similar tasks usually finish in seconds, without waiting in a queue.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon"><?= icon('shield') ?></span>
                <h3>Secure file handling</h3>
                <p>Files travel over HTTPS, are stored under random names in a private folder, and are deleted automatically within <?= (int) $retentionHours ?> hours.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon"><?= icon('cursor') ?></span>
                <h3>Easy to use</h3>
                <p>Drop a file, choose your options, download the result. Every tool works the same way.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon"><?= icon('box') ?></span>
                <h3>No complicated software</h3>
                <p>Nothing to install or update. Everything runs in your web browser.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon"><?= icon('smartphone') ?></span>
                <h3>Mobile friendly</h3>
                <p>Large touch targets and a layout designed for phones, so you can fix a PDF on the go.</p>
            </div>
            <a class="feature-card feature-card-link" href="<?= e(url('security')) ?>">
                <span class="feature-icon feature-icon-dark"><?= icon('lock') ?></span>
                <h3>How we handle your files</h3>
                <p>Read exactly what we store, for how long, and how it is deleted.</p>
                <span class="tool-card-cta">Read security details <?= icon('arrow-right', 'icon icon-sm') ?></span>
            </a>
        </div>
    </div>
</section>

<!-- ===================================================================== -->
<!-- How it works                                                           -->
<!-- ===================================================================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <p class="eyebrow">How it works</p>
            <h2>Three steps to a finished PDF</h2>
        </div>
        <ol class="steps">
            <li class="step">
                <span class="step-num">1</span>
                <span class="step-icon"><?= icon('upload', 'icon icon-lg') ?></span>
                <h3>Upload your file</h3>
                <p>Drag and drop your file or browse from your device.</p>
            </li>
            <li class="step">
                <span class="step-num">2</span>
                <span class="step-icon"><?= icon('settings', 'icon icon-lg') ?></span>
                <h3>Choose your operation</h3>
                <p>Pick the tool and adjust its options — page ranges, quality, passwords and more.</p>
            </li>
            <li class="step">
                <span class="step-num">3</span>
                <span class="step-icon"><?= icon('download', 'icon icon-lg') ?></span>
                <h3>Download your result</h3>
                <p>Get your new file through a private, expiring download link.</p>
            </li>
        </ol>
    </div>
</section>

<!-- ===================================================================== -->
<!-- FAQ                                                                    -->
<!-- ===================================================================== -->
<section class="section">
    <div class="container narrow">
        <div class="section-head">
            <p class="eyebrow">FAQ</p>
            <h2>Common questions</h2>
        </div>
        <div class="faq">
            <?php foreach ($faq as $item): ?>
            <details class="faq-item">
                <summary><?= e($item['q']) ?> <?= icon('chevron-down', 'icon icon-sm') ?></summary>
                <p><?= e($item['a']) ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===================================================================== -->
<!-- CTA                                                                    -->
<!-- ===================================================================== -->
<section class="section cta-section">
    <div class="container">
        <div class="cta-band">
            <div class="cta-copy">
                <h2>Ready to sort out your PDFs?</h2>
                <p>Start with any tool for free. Create an account when you want higher limits and a history of your files.</p>
            </div>
            <div class="cta-actions">
                <a class="btn btn-light btn-lg" href="#tools">Explore PDF Tools</a>
                <a class="btn btn-outline-light btn-lg" href="<?= e(url('register')) ?>">Create free account</a>
            </div>
        </div>
    </div>
</section>
