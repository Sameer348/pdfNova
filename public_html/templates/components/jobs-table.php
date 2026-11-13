<?php
/**
 * Recent files table (dashboard + My Files + admin).
 * Vars: array $jobs, bool $showDownloads (default true — owners only; downloads are owner-checked)
 */

declare(strict_types=1);
?>
<?php $showDownloads = $showDownloads ?? true; ?>
<?php if (!$jobs): ?>
<div class="empty-state">
    <?= icon('folder') ?>
    <p><strong>No files yet</strong></p>
    <p class="small">Files you process while logged in appear here.</p>
    <a class="btn btn-primary btn-sm" href="<?= e(url('/')) ?>#tools">Choose a tool</a>
</div>
<?php else: ?>
<div class="table-wrap">
    <table class="table table-stack">
        <thead><tr><th>Filename</th><th>Tool</th><th>Date</th><th>Status</th><th><span class="visually-hidden">Download</span></th></tr></thead>
        <tbody>
        <?php foreach ($jobs as $job): $download = $showDownloads && $job['status'] === 'completed' ? job_download_url($job) : null; ?>
            <tr>
                <td>
                    <div class="cell-file" title="<?= e($job['result_name'] ?? $job['input_names'] ?? '') ?>"><?= e($job['result_name'] ?? $job['input_names'] ?? '—') ?></div>
                    <?php if ($job['input_names'] && $job['result_name']): ?><div class="cell-sub">from <?= e(mb_strimwidth($job['input_names'], 0, 60, '…')) ?></div><?php endif; ?>
                </td>
                <td data-label="Tool"><?= e(tool_name($job['tool'])) ?></td>
                <td data-label="Date"><?= e(format_date($job['created_at'], 'M j, H:i')) ?></td>
                <td data-label="Status"><?= status_badge($job['status']) ?>
                    <?php if ($job['status'] === 'failed' && $job['error_message']): ?><div class="cell-sub"><?= e($job['error_message']) ?></div><?php endif; ?></td>
                <td>
                    <?php if ($download): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e($download) ?>" download><?= icon('download', 'icon icon-sm') ?> Download</a>
                    <?php elseif ($job['status'] === 'completed' && $showDownloads): ?>
                    <span class="hint" title="Results are deleted automatically for privacy">Deleted</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
