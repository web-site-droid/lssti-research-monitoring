<div class="page-header">
    <h1>Studies</h1>
    <p class="lead">Open a title to see details. Click a column heading to sort the list.</p>
</div>
<?php if ($user['role_slug'] === 'student'): ?>
    <p class="toolbar"><a class="btn btn-primary" href="<?= e(url('studies/create')) ?>"><?= icon('upload') ?> Submit a study</a></p>
<?php endif; ?>
<div class="table-wrap">
<table class="data-table" data-sortable>
    <thead><tr><th>Title</th><th>Program</th><th>Status</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($studies as $s): ?>
        <tr>
            <td><a href="<?= e(url('studies/view', ['id' => $s['id']])) ?>"><?= e($s['short_title']) ?></a></td>
            <td><?= e($s['program_code']) ?></td>
            <td><?= status_badge((string) $s['status']) ?></td>
            <td><?= e(timestamp_label($s['updated_at'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
