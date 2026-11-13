<?php
/**
 * Admin — contact form messages (sent from /contact).
 */

declare(strict_types=1);

set_page(['title' => 'Contact messages']);
$statuses = ['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived', 'spam' => 'Spam'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $new = (string) ($_POST['status'] ?? '');
    if (isset($statuses[$new])) {
        db_exec('UPDATE contact_messages SET status = ?, read_at = COALESCE(read_at, UTC_TIMESTAMP()) WHERE id = ?', [$new, $id]);
        admin_audit('message.status', 'message', $id, ['status' => $new]);
        flash('success', 'Message marked as ' . strtolower($statuses[$new]) . '.');
    }
    redirect(admin_url('messages', ['status' => $_GET['status'] ?? '', 'id' => $new === 'archived' || $new === 'spam' ? null : $id]));
}

$filter = isset($statuses[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$open = (int) ($_GET['id'] ?? 0);
$message = $open ? db_one('SELECT * FROM contact_messages WHERE id = ?', [$open]) : null;
if ($message && $message['status'] === 'new') {
    db_exec("UPDATE contact_messages SET status = 'read', read_at = UTC_TIMESTAMP() WHERE id = ?", [$open]);
    $message['status'] = 'read';
}
$perPage = 30;
$page = admin_page();
$total = (int) db_value('SELECT COUNT(*) FROM contact_messages' . ($filter ? ' WHERE status = ?' : " WHERE status NOT IN ('archived','spam')"), $filter ? [$filter] : []);
$rows = db_all('SELECT id, name, email, subject, status, created_at FROM contact_messages'
    . ($filter ? ' WHERE status = ?' : " WHERE status NOT IN ('archived','spam')") . " ORDER BY id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
    $filter ? [$filter] : []);
?>
<div class="account-head"><div><h1>Contact messages</h1><p class="muted"><?= number_format($total) ?> <?= $filter ? e(strtolower($statuses[$filter])) : 'in the inbox' ?></p></div></div>

<form class="panel filters" method="get" action="<?= e(admin_url('messages')) ?>">
    <?= admin_select('status', ['' => 'Inbox (not archived)'] + $statuses, $filter, 'Status') ?>
    <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
</form>

<?php if ($message): ?>
<div class="panel stack">
    <div class="panel-head"><h2><?= e($message['subject']) ?></h2><span class="badge"><?= e($statuses[$message['status']] ?? $message['status']) ?></span></div>
    <p class="small"><strong><?= e($message['name']) ?></strong> &lt;<a href="mailto:<?= e(rawurlencode($message['email'])) ?>"><?= e($message['email']) ?></a>&gt; · <?= e(format_date($message['created_at'])) ?> · IP <?= e(masked_ip($message['ip_address'])) ?></p>
    <div class="message-body"><?= e($message['message']) ?></div>
    <div class="row">
        <?php foreach (['replied' => 'Mark replied', 'archived' => 'Archive', 'spam' => 'Spam'] as $s => $label): ?>
        <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $message['id'] ?>"><input type="hidden" name="status" value="<?= e($s) ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= e($label) ?></button></form>
        <?php endforeach; ?>
        <a class="btn btn-primary btn-sm" href="mailto:<?= e(rawurlencode($message['email'])) ?>?subject=<?= e(rawurlencode('Re: ' . $message['subject'])) ?>">Reply by email</a>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <?php if (!$rows): ?><p class="muted">No messages.</p><?php else: ?>
    <div class="table-wrap"><table class="table table-stack">
        <thead><tr><th>From</th><th>Subject</th><th>Status</th><th>Received</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <td><div class="cell-file"><?= e($r['name']) ?></div><div class="cell-sub"><?= e($r['email']) ?></div></td>
                <td data-label="Subject"><a href="<?= e(admin_url('messages', ['id' => $r['id'], 'status' => $filter])) ?>"><?= $r['status'] === 'new' ? '<strong>' . e($r['subject']) . '</strong>' : e($r['subject']) ?></a></td>
                <td data-label="Status"><span class="badge<?= $r['status'] === 'new' ? ' badge-red' : '' ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
                <td data-label="Received"><?= e(format_date($r['created_at'], 'M j, H:i')) ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= admin_pager($page, $total, $perPage, 'messages', ['status' => $filter]) ?>
    <?php endif; ?>
</div>
