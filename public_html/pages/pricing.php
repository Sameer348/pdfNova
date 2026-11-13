<?php
/**
 * /pricing — plans are read from the `plans` table (editable in the admin),
 * and every feature shown is generated from the limits the code enforces.
 */

declare(strict_types=1);

use PDFNova\Payment\PaymentFactory;

$plans   = db_all('SELECT * FROM plans WHERE is_active = 1 AND is_public = 1 ORDER BY sort_order');
$user    = current_user();
$current = $user ? current_plan()['slug'] : null;
$gateway = PaymentFactory::make();
$symbol  = static fn(string $cur): string => $cur === 'USD' ? '$' : $cur . ' ';
$fmt     = static fn(float $amount): string => number_format($amount, fmod($amount, 1.0) ? 2 : 0);
$tagline = ['free' => 'For occasional use', 'pro' => 'For professionals', 'business' => 'For teams and heavy use'];

set_page([
    'title'       => 'Pricing – Free, Pro & Business plans | PDFNova',
    'description' => 'Compare PDFNova plans. Start free; upgrade for larger files, more files per task and unlimited tasks.',
    'body_class'  => 'page-pricing',
]);
?>
<section class="page-hero">
    <div class="container narrow">
        <p class="eyebrow">Pricing</p>
        <h1 class="mt-4">Simple plans for every workload</h1>
        <p class="lead">Start free. Upgrade when you need bigger files and more tasks.</p>
    </div>
</section>

<section class="section pricing-section">
    <div class="container">
        <div class="pricing">
            <div class="cycle-toggle" role="radiogroup" aria-label="Billing period">
                <input type="radio" name="cycle" id="cycle-monthly" checked>
                <label for="cycle-monthly">Monthly</label>
                <input type="radio" name="cycle" id="cycle-yearly">
                <label for="cycle-yearly">Yearly <span class="badge badge-success">Save ~20%</span></label>
            </div>

            <div class="plan-grid">
                <?php foreach ($plans as $p):
                    $free = (int) $p['price_monthly_cents'] === 0;
                    $featured = $p['slug'] === 'pro';
                    $monthly = (int) $p['price_monthly_cents'] / 100;
                    $yearlyPerMonth = (int) $p['price_yearly_cents'] / 100 / 12;
                ?>
                <article class="plan-card<?= $featured ? ' is-featured' : '' ?>">
                    <?php if ($featured): ?><span class="plan-ribbon">Most popular</span><?php endif; ?>
                    <h2 class="plan-name"><?= e($p['name']) ?></h2>
                    <p class="plan-tagline"><?= e($tagline[$p['slug']] ?? (string) $p['description']) ?></p>
                    <div class="plan-price">
                        <?php if ($free): ?>
                            <span class="plan-amount">Free</span><span class="plan-period">forever</span>
                        <?php else: ?>
                            <span class="price-monthly"><span class="plan-amount"><?= e($symbol($p['currency']) . $fmt($monthly)) ?></span><span class="plan-period">/ month</span></span>
                            <span class="price-yearly"><span class="plan-amount"><?= e($symbol($p['currency']) . $fmt(round($yearlyPerMonth, 2))) ?></span><span class="plan-period">/ month, billed <?= e($symbol($p['currency']) . $fmt((int) $p['price_yearly_cents'] / 100)) ?> yearly</span></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($current === $p['slug']): ?>
                        <span class="btn btn-secondary btn-block" aria-disabled="true">Your current plan</span>
                    <?php elseif ($free): ?>
                        <a class="btn btn-secondary btn-block" href="<?= e(url($user ? 'dashboard' : 'register')) ?>"><?= $user ? 'Go to dashboard' : 'Create free account' ?></a>
                    <?php elseif ($gateway->isAvailable()): ?>
                        <a class="btn btn-primary btn-block" href="<?= e(url('dashboard/subscription')) ?>">Upgrade to <?= e($p['name']) ?></a>
                    <?php else: ?>
                        <a class="btn <?= $featured ? 'btn-primary' : 'btn-secondary' ?> btn-block" href="<?= e(url('contact')) ?>">Contact us to upgrade</a>
                    <?php endif; ?>

                    <?php partial('components/plan-features', ['plan' => $p]); ?>
                </article>
                <?php endforeach; ?>
            </div>

            <p class="pricing-note">
                <?php if (!$gateway->isAvailable()): ?>
                Online payment isn't available yet — paid plans are set up by our team when you contact us.
                <?php endif; ?>
                Guests (no account) can use the tools with smaller limits. Per-file limits also depend on the server's upload limit.
            </p>
        </div>
    </div>
</section>
