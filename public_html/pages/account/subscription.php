<?php
/**
 * /dashboard/subscription — current plan and upgrade options.
 */

declare(strict_types=1);

use PDFNova\Payment\PaymentFactory;

$user    = require_login();
$plan    = current_plan();
$plans   = db_all('SELECT * FROM plans WHERE is_active = 1 AND is_public = 1 ORDER BY sort_order');
$gateway = PaymentFactory::make();
$sub     = db_one("SELECT * FROM subscriptions WHERE user_id = ? AND status IN ('active','trialing') ORDER BY id DESC LIMIT 1", [$user['id']]);
$money   = static fn(int $cents, string $cur): string => ($cur === 'USD' ? '$' : $cur . ' ') . number_format($cents / 100, $cents % 100 ? 2 : 0);

set_page(['title' => 'Subscription | PDFNova', 'robots' => 'noindex,nofollow', 'body_class' => 'page-account']);
?>
<section class="account">
    <div class="container account-grid">
        <?php partial('components/account-nav', ['active' => 'subscription', 'user' => $user]); ?>
        <div class="account-main">
            <div class="account-head"><div><h1>Subscription</h1><p class="muted">Your plan and its limits.</p></div></div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Current plan: <?= e($plan['name']) ?></h2>
                    <span class="badge badge-success">Active</span>
                </div>
                <?php if ($sub && $sub['current_period_end']): ?>
                <p class="small muted">Renews or ends on <?= e(format_date($sub['current_period_end'], 'M j, Y')) ?>.</p>
                <?php endif; ?>
                <?php partial('components/plan-features', ['plan' => $plan]); ?>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>Change plan</h2></div>
                <?php if (!$gateway->isAvailable()): ?>
                <div class="alert alert-info mb-4"><?= icon('info') ?><p>Online payment isn't available yet. To upgrade, <a href="<?= e(url('contact')) ?>">contact us</a> and we'll set up your plan manually.</p></div>
                <?php endif; ?>
                <div class="grid grid-3">
                    <?php foreach ($plans as $p): $isCurrent = $p['slug'] === $plan['slug']; ?>
                    <div class="plan-mini<?= $isCurrent ? ' is-current' : '' ?>">
                        <p class="plan-mini-name"><?= e($p['name']) ?></p>
                        <p class="plan-mini-price"><?= (int) $p['price_monthly_cents'] === 0 ? 'Free' : e($money((int) $p['price_monthly_cents'], $p['currency'])) . '<span>/month</span>' ?></p>
                        <?php if ($isCurrent): ?>
                            <span class="btn btn-secondary btn-sm" aria-disabled="true">Your plan</span>
                        <?php elseif ((int) $p['price_monthly_cents'] === 0): ?>
                            <span class="hint">Contact us to downgrade</span>
                        <?php elseif ($gateway->isAvailable()): ?>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('pricing')) ?>">Upgrade</a>
                        <?php else: ?>
                            <a class="btn btn-soft btn-sm" href="<?= e(url('contact')) ?>">Contact us to upgrade</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
