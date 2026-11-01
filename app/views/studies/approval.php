<div class="page-header">
    <h1>Waiting</h1>
    <p class="lead">Wait here until your teacher accepts your study. Then send it to the library.</p>
</div>
<div class="table-wrap">
<table class="data-table" data-sortable>
    <thead>
        <tr>
            <th>Title</th>
            <th>Type</th>
            <th>Adviser</th>
            <th>Department</th>
            <th>Status</th>
            <th data-nosort>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$studies): ?>
        <tr><td colspan="6">No studies are waiting for adviser approval.</td></tr>
    <?php endif; ?>
    <?php foreach ($studies as $study): ?>
        <tr>
            <td><?= e($study['title']) ?></td>
            <td><?= e(study_type_label($study['study_type'] ?? '')) ?></td>
            <td><?= e(trim(($study['adviser_last'] ?? '') . ', ' . ($study['adviser_first'] ?? ''), ' ,') ?: '—') ?></td>
            <td><?= e($study['program_code']) ?></td>
            <td>
                <?php if ($study['status'] === 'revision_requested'): ?>
                    <span class="status-pill is-off">Revision requested</span>
                <?php elseif ($study['status'] === 'adviser_reviewed'): ?>
                    <span class="status-pill is-on">Approved</span>
                <?php else: ?>
                    <span class="status-pill is-study">Pending</span>
                <?php endif; ?>
            </td>
            <td class="actions-cell">
                <div class="actions">
                <?php if ($study['status'] === 'revision_requested'): ?>
                    <a class="btn btn-secondary btn-icon-only" href="<?= e(url('studies/edit', ['id' => $study['id']])) ?>" aria-label="Edit" title="Edit"><?= icon('pencil') ?></a>
                <?php elseif ($study['status'] === 'adviser_reviewed'): ?>
                    <form method="post" action="<?= e(url('studies/submit-librarian')) ?>">
                        <?= \App\Helpers\Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int) $study['id'] ?>">
                        <button type="submit" class="btn btn-green btn-sm"><?= icon('send') ?> Send to dept research coordinator</button>
                    </form>
                <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
