<?php
/**
 * Admin — temporary files (metadata) with the option to delete one now.
 * File contents are never shown or downloadable here.
 */

declare(strict_types=1);

set_page(['title' => 'Files']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $file = db_one("SELECT * FROM files WHERE id = ? AND status = 'active'", [$id]);
    if ($file) {
        delete_stored_file($file);
        admin_audit('file.deleted', 'file', $id, ['kind' => $file['kind'], 'size' => (int) $file['size_bytes']]);
        flash('success', 'File deleted from storage.');
    }
    redirect(admin_url('files', array_intersect_key($_GET, ['status' => 1, 'kind' => 1, 'page' => 1])));
}

$status = in_array($_GET['status'] ?? '', ['active', 'deleted'], true) ? $_GET['status'] : 'active';
$kind = in_array($_GET['kind'] ?? '', ['upload', 'result'], true) ? $_GET['kind'] : '';
$perPage = 30;
$page = admin_page();
$where = 'f.status = ?' . ($kind ? ' AND f.kind = ?' : '');
$params = $kind ? [$status, $kind] : [$status];
$total = (int) db_value("SELECT COUNT(*) FROM files f WHERE $where", $params);
$files = db_all(
    "SELECT f.id, f.original_name, f.kind, f.extension, f.size_bytes, f.page_count, f.created_at, f.expires_at, f.deleted_at, u.email
       FROM files f LEFT JOIN users u ON u.id = f.user_id WHERE $where ORDER BY f.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
    $params
);
$filters = ['status' => $status, 'kind' => $kind];
?>
<div class="account-head"><div><h1>Files</h1><p class="muted">Temporary uploads and results. Expired files are removed automatically by the cleanup job.</p></div></div>

<form class="panel filters" method="get" action="<?= e(admin_url('files')) ?>">
    <?= admin_select('status', ['active' => 'Stored now', 'deleted' => 'Deleted'], $status, 'Status') ?>
    <?= admin_select('kind', ['' => 'Uploads and results', 'upload' => 'Uploads', 'result' => 'Results'], $kind, 'Kind') ?>
    <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
</form>

<div class="panel">
    <?php if (!$files): ?><p class="muted">No files.</p><?php else: ?>
    <div class="table-wrap"><table class="table table-stack">
        <thead><tr><th>File</th><th>Kind</th><th>Owner</th><th>Size</th><th>Created</th><th><?= $status === 'active' ? 'Expires' : 'Deleted' ?></th><th></th></tr></thead>
        <tbody><?php foreach ($files as $f): ?>
            <tr>
                <td><div class="cell-file" title="<?= e($f['original_name']) ?>"><?= e($f['original_name']) ?></div><div class="cell-sub"><?= e(strtoupper($f['extension'])) ?><?= $f['page_count'] ? ' · ' . (int) $f['page_count'] . ' pages' : '' ?></div></td>
                <td data-label="Kind"><?= e(ucfirst($f['kind'])) ?></td>
                <td data-label="Owner"><?= e($f['email'] ?? 'Guest') ?></td>
                <td data-label="Size"><?= e(format_bytes((int) $f['size_bytes'])) ?></td>
                <td data-label="Created"><?= e(format_date($f['created_at'], 'M j, H:i')) ?></td>
                <td data-label="<?= $status === 'active' ? 'Expires' : 'Deleted' ?>"><?= e(format_date($status === 'active' ? $f['expires_at'] : $f['deleted_at'], 'M j, H:i')) ?></td>
                <td><?php if ($status === 'active'): ?>
                    <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Delete now</button></form>
                <?php endif; ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= admin_pager($page, $total, $perPage, 'files', $filters) ?>
    <?php endif; ?>
</div>
