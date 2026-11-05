<div class="page-header"><h1>Librarian activity logs</h1><p class="lead">Recorded sign-ins, sign-outs, and system actions by Librarian accounts. Showing the latest 200 events.</p></div>
<form method="get" class="filter-bar">
    <input type="hidden" name="route" value="admin/activity">
    <label for="activity-user">Librarian</label><select id="activity-user" name="user_id"><option value="">All Librarians</option>
    <?php foreach ($accounts as $account): ?><option value="<?= (int) $account['id'] ?>" <?= $selectedId === (int) $account['id'] ? 'selected' : '' ?>><?= e($account['school_id'] . ' - ' . $account['first_name'] . ' ' . $account['last_name']) ?></option><?php endforeach; ?>
    </select><button class="btn btn-secondary" type="submit">Apply</button><a href="<?= e(url('admin/activity')) ?>">Reset</a>
</form>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Date and time</th><th>ID no.</th><th>Librarian</th><th>Activity</th></tr></thead><tbody>
<?php if (!$logs): ?><tr><td colspan="4">No recorded activity for this selection.</td></tr><?php endif; ?>
<?php foreach ($logs as $log): ?><tr><td><?= e($log['created_at']) ?></td><td><?= e($log['school_id']) ?></td><td><?= e($log['first_name'] . ' ' . $log['last_name']) ?></td><td><?= e(ucwords(str_replace(['.', '_'], ' ', $log['action']))) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
