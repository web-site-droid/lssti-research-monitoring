<div class="page-header">
    <h1>Sign-in history</h1>
    <p class="lead">Recent sign-ins. Click a column heading to sort.</p>
</div>
<label class="table-search">Search history
    <input type="search" data-table-filter="#signins-table" placeholder="Name or ID no.">
</label>
<p id="signins-table-empty" class="empty-state" hidden>No sign-ins match that search.</p>
<div class="table-wrap">
<table class="data-table" data-sortable id="signins-table">
    <thead><tr><th>Time</th><th>Name</th><th>ID no.</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= e($log['created_at']) ?></td>
            <td><?= e(trim(($log['last_name'] ?? '') . ' ' . ($log['first_name'] ?? ''))) ?></td>
            <td><?= e($log['school_id_attempt'] ?? $log['school_id'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
