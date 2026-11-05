<div class="page-header">
    <h1>Department review</h1>
    <p class="lead">Review papers awaiting your department's approval. Save a review note, then forward the study to the Dean.</p>
</div>
<?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<?php partial('find_bar', [
    'table' => '#department-review-table',
    'papers' => $studies,
    'showDepartment' => false,
]); ?>
<div class="table-scroll adviser-results<?= count($studies) > 10 ? ' is-scroll' : '' ?>">
<div class="table-wrap">
<table class="dash-table review-table" id="department-review-table">
    <thead><tr><th>Study</th><th>Submitted by</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php if (!$studies): ?>
        <tr class="dash-empty-row"><td colspan="4">You're up to date. No studies are awaiting department review.</td></tr>
    <?php endif; ?>
    <?php foreach ($studies as $paper): ?>
        <tr<?= paper_find_attrs($paper) ?>>
            <td class="review-study-title">
                <a class="review-title-link" href="<?= e(url('library/review', ['id' => $paper['id']])) ?>"><?= e($paper['title']) ?></a>
                <span class="review-study-meta"><?= e($paper['school_year'] ?? '') ?> &middot; <?= e(study_type_label($paper['study_type'] ?? '')) ?></span>
            </td>
            <td><?= e(trim(($paper['submitter_first'] ?? '') . ' ' . ($paper['submitter_last'] ?? ''))) ?></td>
            <td><?= status_badge((string) $paper['status']) ?></td>
            <td><a class="btn btn-secondary" href="<?= e(url('library/review', ['id' => $paper['id']])) ?>">Review study</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
