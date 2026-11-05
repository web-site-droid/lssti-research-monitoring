<?php
$adviserQueue = !empty($adviserQueue);
$deanQueue = !empty($deanQueue);
$adminQueue = !empty($adminQueue);
$ownDepartment = $adviserQueue || $deanQueue;
?>
<div class="page-header">
    <h1><?= $adviserQueue && in_array($decisionFilter ?? '', ['pending', 'accepted'], true) ? ucfirst($decisionFilter) . ' reviews' : 'Reviews' ?></h1>
    <?php if ($adviserQueue && !empty($decisionFilter)): ?><a href="<?= e(url('review')) ?>">View all reviews</a><?php endif; ?>
    <p class="lead"><?= $adviserQueue
        ? 'Accept a student study so they can send it to the dept research coordinator.'
        : ($deanQueue
            ? 'Approve studies from the dept research coordinator for the school research coordinator.'
            : ($adminQueue
                ? 'Publish studies cleared by the school research coordinator.'
                : (!empty($seeAllPapers)
                    ? 'Complete checks, then send studies to the school administrator.'
                    : 'Check a study, then accept or reject it.'))) ?></p>
</div>
<?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<?php partial('find_bar', [
    'table' => '#review-studies',
    'cards' => $adviserQueue ? '' : '#review-read-cards',
    'papers' => $studies,
    'statusOptions' => $adviserQueue ? ['' => 'Any status', 'pending' => 'Pending', 'accepted' => 'Accepted'] : [],
    'statusAttr' => 'data-decision',
    'showDepartment' => !$ownDepartment,
]); ?>
<?php if (!$adviserQueue): ?>
<p class="muted">Click a card to read the whole file and play it aloud.</p>
<div class="paper-cards" id="review-read-cards">
    <?php foreach ($studies as $paper): ?>
        <?php if (can_open_full_file($user ?? \App\Helpers\Auth::user(), $paper)):
            $cardDecision = $adviserQueue && !in_array($paper['status'], ['submitted', 'revision_requested'], true) ? 'accepted' : ($adviserQueue ? 'pending' : '');
            partial('paper_read_card', ['paper' => $paper, 'decision' => $cardDecision]);
        endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="table-scroll<?= $adviserQueue ? ' adviser-results' : '' ?><?= count($studies) > 10 ? ' is-scroll' : '' ?>">
<div class="table-wrap dash-board">
<table class="dash-table review-table<?= $ownDepartment ? ' is-own-dept' : '' ?><?= $adviserQueue ? ' adviser-results-table' : '' ?>" id="review-studies">
    <thead>
        <tr>
            <th>Title</th>
            <th>Submitted by</th>
            <th>Authors</th>
            <?php if (!$ownDepartment): ?>
            <th>Department</th>
            <?php endif; ?>
            <th><?= $adviserQueue ? 'Status / current stage' : 'Academic year' ?></th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$studies): ?>
        <tr class="dash-empty-row"><td><?= $adviserQueue ? 'No pending or accepted studies.' : 'No studies are waiting.' ?></td><td></td><td></td><?php if (!$ownDepartment): ?><td></td><?php endif; ?><td></td><td></td></tr>
    <?php endif; ?>
    <?php foreach ($studies as $s): ?>
        <?php $accepted = $adviserQueue && !in_array($s['status'], ['submitted', 'revision_requested'], true); ?>
        <tr<?= paper_find_attrs($s) ?> data-decision="<?= $adviserQueue ? ($accepted ? 'accepted' : 'pending') : '' ?>">
            <td class="review-study-title">
                <a class="review-title-link" href="<?= e(url('studies/view', ['id' => $s['id']])) ?>"><?= e($s['title']) ?></a>
                <span class="review-study-meta"><?= e($s['school_year'] ?? '') ?> &middot; <?= e(study_type_label($s['study_type'] ?? '')) ?></span>
            </td>
            <td><?= e(trim(($s['submitter_last'] ?? '') . ', ' . ($s['submitter_first'] ?? ''), ' ,') ?: '—') ?></td>
            <td><?= author_cell($s['authors'] ?? '', (string) $s['title']) ?></td>
            <?php if (!$ownDepartment): ?>
            <td><?= e($s['program_code']) ?></td>
            <?php endif; ?>
            <td>
                <?php if ($adviserQueue): ?>
                    <?= status_badge((string) $s['status'], ($s['status'] ?? '') === 'submitted' ? 'Pending - with Research Adviser' : null) ?>
                <?php else: ?>
                    <?= e($s['school_year'] ?? '') ?>
                <?php endif; ?>
            </td>
            <td class="actions-cell">
                <?php
                ob_start();
                echo '<button type="button" class="btn btn-secondary btn-icon-only" data-study-quick'
                    . ' data-title="' . e($s['title']) . '"'
                    . ' data-meta="' . e(trim(($s['authors'] ?: '—') . ' · Submitted by ' . trim(($s['submitter_last'] ?? '') . ', ' . ($s['submitter_first'] ?? ''), ' ,') . ' · ' . ($s['program_code'] ?? '') . ' · ' . ($s['school_year'] ?? ''), ' ·')) . '"'
                    . ' data-abstract="' . e($s['abstract'] ?? '') . '"'
                    . ' data-objectives="' . e($s['research_objectives'] ?? '') . '"'
                    . ' aria-label="View" title="View">' . icon('eye') . '</button>';
                if (can_open_full_file($user, $s)) {
                    echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('reader', ['id' => $s['id']])) . '" aria-label="View full file" title="View full file">' . icon('book') . '</a>';
                    echo '<a class="btn btn-green btn-icon-only" href="' . e(url('file/manuscript', ['study_id' => $s['id'], 'version_id' => $s['current_version_id']])) . '" aria-label="Download" title="Download">' . icon('download') . '</a>';
                }
                if ($adviserQueue) {
                    if ($s['status'] === 'submitted') {
                        echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('review/ethics', ['id' => $s['id']])) . '" aria-label="Open review" title="Open review">' . icon('check') . '</a>';
                    }
                } else {
                    if ($deanQueue && ($s['status'] ?? '') === 'dean_review') {
                        echo '<form method="post" action="' . e(url('review/dean-approve')) . '">'
                            . \App\Helpers\Csrf::field()
                            . '<input type="hidden" name="study_id" value="' . (int) $s['id'] . '">'
                            . '<button type="submit" class="btn btn-green btn-icon-only" aria-label="Approve for school" title="Approve for school">' . icon('check') . '</button></form>';
                    }
                    if (($s['status'] ?? '') === 'under_review') {
                        echo '<form method="post" action="' . e(url('review/approve')) . '">'
                            . \App\Helpers\Csrf::field()
                            . '<input type="hidden" name="study_id" value="' . (int) $s['id'] . '">'
                            . '<button type="submit" class="btn btn-green btn-icon-only" aria-label="Send to school administrator" title="Send to school administrator">' . icon('check') . '</button></form>';
                        echo '<form method="post" action="' . e(url('review/reject')) . '" data-confirm="Do you want to reject this study?">'
                            . \App\Helpers\Csrf::field()
                            . '<input type="hidden" name="study_id" value="' . (int) $s['id'] . '">'
                            . '<button type="submit" class="btn btn-danger btn-icon-only" aria-label="Reject" title="Reject">' . icon('x') . '</button></form>';
                    }
                    if ($adminQueue && in_array($s['status'] ?? '', ['admin_review', 'library_review'], true)) {
                        echo '<form method="post" action="' . e(url('review/final-publish')) . '">'
                            . \App\Helpers\Csrf::field()
                            . '<input type="hidden" name="study_id" value="' . (int) $s['id'] . '">'
                            . '<button type="submit" class="btn btn-green btn-icon-only" aria-label="Publish" title="Publish">' . icon('check') . '</button></form>';
                    }
                }
                echo action_menu(ob_get_clean());
                ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($studies): ?>
        <tr class="review-empty-row" hidden>
            <td>No studies match this search.</td>
            <td></td><td></td><?php if (!$ownDepartment): ?><td></td><?php endif; ?><td></td><td></td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
</div>
<?php require APP_PATH . '/views/partials/authors_modal.php'; ?>
<dialog id="study-quick" class="preview-modal" aria-labelledby="study-quick-heading">
    <div class="preview-modal-head">
        <h2 id="study-quick-heading">Quick view</h2>
        <button type="button" class="btn btn-secondary btn-icon-only" id="study-quick-close" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <h3 id="study-quick-title"></h3>
    <p class="scholar-meta" id="study-quick-meta"></p>
    <p id="study-quick-abstract"></p>
    <div id="study-quick-objectives-wrap" hidden>
        <h4>Research objectives</h4>
        <p id="study-quick-objectives"></p>
    </div>
</dialog>
