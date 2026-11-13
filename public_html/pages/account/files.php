<?php
/**
 * /dashboard/files — full history of processed files (paginated).
 */

declare(strict_types=1);

$user = require_login();
$plan = current_plan();
$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = account_job_count((int) $user['id'], (int) $plan['history_days']);
$jobs = account_jobs((int) $user['id'], (int) $plan['history_days'], $perPage, ($page - 1) * $perPage);

set_page(['title' => 'My files | PDFNova', 'robots' => 'noindex,nofollow', 'body_class' => 'page-account']);
?>
<section class="account">
    <div class="container account-grid">
        <?php partial('components/account-nav', ['active' => 'files', 'user' => $user]); ?>
        <div class="account-main">
            <div class="account-head">
                <div>
                    <h1>My files</h1>
                    <p class="muted"><?= number_format($total) ?> <?= $total === 1 ? 'task' : 'tasks' ?> in the last <?= (int) $plan['history_days'] ?> days</p>
                </div>
            </div>
            <div class="panel">
                <?php partial('components/jobs-table', ['jobs' => $jobs]); ?>
                <?= pager($page, $total, $perPage, 'dashboard/files') ?>
            </div>
        </div>
    </div>
</section>
