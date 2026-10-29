<div class="page-header">
    <h1>Logs</h1>
    <p class="lead"><?= !empty($showDownloads) ? 'See who signed in and who downloaded a file.' : 'See who signed in.' ?></p>
</div>
<section class="card card-elevated paper-group is-wide">
    <h2 class="section-title">Who signed in</h2>
    <?php if (($signIns ?? []) === []): ?>
        <p class="empty-state">No one has signed in yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Name</th><th>School ID</th><th>Role</th><th>Department</th><th>Last sign-in</th></tr></thead>
                <tbody>
                <?php foreach ($signIns as $row): ?>
                    <tr>
                        <td><?= e($row['last_name'] . ', ' . $row['first_name']) ?></td>
                        <td><?= e($row['school_id']) ?></td>
                        <td><?= e($row['role_name']) ?></td>
                        <td><?= e($row['program_code'] ?? '') ?></td>
                        <td><?= e((string) $row['last_login']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php if (!empty($showDownloads)): ?>
<section class="card card-elevated paper-group is-wide">
    <h2 class="section-title">Who downloaded a file</h2>
    <label class="table-search">Search downloads
        <input type="search" data-table-filter="#downloads-table" placeholder="Name, school ID, or file">
    </label>
    <p id="downloads-table-empty" class="empty-state" hidden>No downloads match that search.</p>
    <?php if (($downloads ?? []) === []): ?>
        <p class="empty-state">No one has downloaded a file yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-sortable id="downloads-table">
                <thead><tr><th>Name</th><th>School ID</th><th>Role</th><th>Department</th><th>File</th><th>Downloaded</th></tr></thead>
                <tbody>
                <?php foreach ($downloads as $row): ?>
                    <tr>
                        <td><?= e($row['last_name'] . ', ' . $row['first_name']) ?></td>
                        <td><?= e($row['school_id']) ?></td>
                        <td><?= e($row['role_name']) ?></td>
                        <td><?= e($row['program_code'] ?: 'All departments') ?></td>
                        <td><?= e($row['title'] ?: 'Removed study') ?></td>
                        <td><?= e((string) $row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
