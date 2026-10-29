<div class="page-header">
    <h1>Who opened this study<?php if ($study): ?>: <?= e($study['short_title']) ?><?php endif; ?></h1>
    <p class="lead">Filter by study and dates. Click a heading to sort the list.</p>
</div>
<form method="get" action="<?= e(url('reports/read-log')) ?>" class="filter-bar">
    <label>Study number <input name="study_id" value="<?= (int) ($study['id'] ?? 0) ?>" required></label>
    <label>From <input type="date" name="from" value="<?= e($from ?? '') ?>"></label>
    <label>To <input type="date" name="to" value="<?= e($to ?? '') ?>"></label>
    <button type="submit" class="btn btn-secondary"><?= icon('filter') ?> Apply</button>
</form>
<div class="table-wrap">
<table class="data-table" data-sortable>
    <thead><tr><th>Time</th><th>User</th><th>Role</th><th>IP address</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= e($log['opened_at']) ?></td>
            <td><?= e($log['school_id'] . ' — ' . $log['last_name']) ?></td>
            <td><?= e($log['role_name']) ?></td>
            <td><?= e($log['ip_address']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
