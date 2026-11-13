<?php
/**
 * ToolCard component.
 * Vars: string $slug, array $tool
 */

declare(strict_types=1);

$status = tool_status($tool);
$off = $status['state'] === 'unavailable';
?>
<a class="tool-card<?= $off ? ' is-unavailable' : '' ?>" href="<?= e(url($slug)) ?>">
    <?php if ($off): ?><span class="badge">Coming soon</span><?php endif; ?>
    <span class="tool-icon <?= e(tool_tone($tool)) ?>"><?= icon($tool['icon']) ?></span>
    <h3><?= e($tool['name']) ?></h3>
    <p><?= e($tool['short']) ?></p>
    <span class="tool-card-cta"><?= $off ? 'Learn more' : 'Open tool' ?> <?= icon('arrow-right', 'icon icon-sm') ?></span>
</a>
