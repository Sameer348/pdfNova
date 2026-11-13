<?php
/**
 * Account sidebar. Vars: string $active, array $user
 */

declare(strict_types=1);

$items = [
    'dashboard'    => ['Dashboard', 'dashboard', 'dashboard'],
    'files'        => ['My Files', 'dashboard/files', 'folder'],
    'activity'     => ['Recent Activity', 'dashboard/activity', 'activity'],
    'subscription' => ['Subscription', 'dashboard/subscription', 'credit-card'],
    'account'      => ['Account', 'account', 'user'],
    'settings'     => ['Settings', 'dashboard/settings', 'settings'],
];
?>
<aside class="account-nav" aria-label="Account">
    <div class="account-user">
        <span class="account-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
        <div class="account-user-meta">
            <p class="account-user-name"><?= e($user['name']) ?></p>
            <p class="hint"><?= e($user['email']) ?></p>
        </div>
    </div>
    <nav>
        <ul>
            <?php foreach ($items as $key => [$label, $path, $iconName]): ?>
            <li><a class="account-link<?= $key === $active ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                <?= icon($iconName, 'icon icon-sm') ?> <?= e($label) ?></a></li>
            <?php endforeach; ?>
            <li>
                <form method="post" action="<?= e(url('logout')) ?>">
                    <?= csrf_field() ?>
                    <button class="account-link" type="submit"><?= icon('logout', 'icon icon-sm') ?> Logout</button>
                </form>
            </li>
        </ul>
    </nav>
</aside>
