<div class="page-header">
    <h1>Activity log</h1>
    <p class="lead">Who changed what, and when. Click a column heading to sort.</p>
</div>
<form method="get" action="<?= e(url('reports/audit-trail')) ?>" class="filter-bar">
    <label>From <input type="date" name="from" value="<?= e($from ?? '') ?>"></label>
    <label>To <input type="date" name="to" value="<?= e($to ?? '') ?>"></label>
    <button type="submit" class="btn btn-secondary"><?= icon('calendar') ?> Apply dates</button>
</form>
<div class="table-wrap">
<table class="data-table" data-sortable>
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Item</th><th>IP address</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= e(timestamp_label($log['created_at'])) ?></td>
            <td><?= e(trim(($log['school_id'] ?? '') . ' ' . ($log['last_name'] ?? ''))) ?></td>
            <td><?= e($log['action']) ?></td>
            <td><?= e(($log['entity_type'] ?? '') . ' #' . ($log['entity_id'] ?? '')) ?></td>
            <td><?= e($log['ip_address']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
