<?php
/**
 * Feature list for a plan card — generated from the plans table so it can
 * never promise more than the code enforces. Vars: array $plan
 */

declare(strict_types=1);

$features = [
    ['ok', 'Up to ' . (int) $plan['max_file_size_mb'] . ' MB per file'],
    ['ok', 'Up to ' . (int) $plan['max_files_per_job'] . ' files per task'],
    ['ok', $plan['daily_jobs_limit'] === null ? 'Unlimited tasks' : (int) $plan['daily_jobs_limit'] . ' tasks per 24 hours'],
    ['ok', (int) $plan['history_days'] . '-day file history'],
    ['ok', 'All available PDF tools'],
];
if ((int) $plan['batch_processing'] === 1) {
    $features[] = ['ok', 'Batch processing (many files per task)'];
}
if ((int) $plan['team_seats'] > 1) {
    $features[] = ['soon', 'Team seats (' . (int) $plan['team_seats'] . ' users) — coming soon'];
}
if ((int) $plan['api_access'] === 1) {
    $features[] = ['soon', 'API access — coming soon'];
}
?>
<ul class="plan-features">
    <?php foreach ($features as [$kind, $text]): ?>
    <li class="<?= $kind === 'soon' ? 'is-soon' : '' ?>"><?= icon($kind === 'soon' ? 'clock' : 'check', 'icon icon-sm') ?> <?= e($text) ?></li>
    <?php endforeach; ?>
</ul>
