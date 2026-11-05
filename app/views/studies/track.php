<?php
$adviserTrack = !empty($adviserTrack);
$waitingStudies = $waitingStudies ?? [];
?>
<?php $waitingView = !$adviserTrack && (($trackTab ?? '') === 'waiting'); ?>
<div class="page-header">
    <h1><?= $waitingView ? 'Waiting' : 'Track' ?></h1>
    <p class="lead"><?= $adviserTrack
        ? 'See studies you sent to the library.'
        : ($waitingView
            ? 'Wait here until your teacher accepts your study. Then send it to the dept research coordinator.'
            : 'See where your study is after your teacher accepts it.') ?></p>
</div>
<?php
$tableStudies = [];
$tracking = [];
$hidden = $adviserTrack
    ? ['draft', 'revision_requested']
    : ['draft', 'submitted', 'resubmitted', 'revision_requested'];
foreach ($studies as $study) {
    if (in_array($study['status'], $hidden, true)) {
        continue;
    }
    $tableStudies[] = $study;
    $tracking[] = $study;
}
$statusLabel = static function (string $status): string {
    return match ($status) {
        'approved' => 'Published',
        'submitted', 'resubmitted' => 'With research adviser',
        'adviser_reviewed' => 'Approved, send to dept research coordinator',
        'pending' => 'With dept research coordinator',
        'dean_review' => 'With dean',
        'under_review', 'ethics_review' => 'With school research coordinator',
        'library_review' => 'With librarian',
        'admin_review' => 'With librarian',
        'archived' => 'Not approved',
        default => 'In review',
    };
};
$hasAny = $tableStudies !== [] || $waitingStudies !== [];
$trackTime = static function (array $paper): string {
    if (($paper['status'] ?? '') === 'approved') return (string) ($paper['approved_at'] ?? '');
    return (string) ($paper['updated_at'] ?? $paper['submitted_at'] ?? $paper['created_at'] ?? '');
};
usort($tracking, static fn (array $a, array $b): int => strcmp($trackTime($b), $trackTime($a)));
$trackingGroups = [];
foreach ($tracking as $paper) {
    $stamp = $trackTime($paper);
    $trackingGroups[$stamp !== '' ? substr($stamp, 0, 10) : ''][] = $paper;
}
?>
<?php if ($waitingView): ?>
<?php if ($waitingStudies === []): ?>
    <p class="empty-state">No studies are waiting for adviser approval.</p>
    <a class="btn btn-primary" href="<?= e(url('studies/create')) ?>"><?= icon('upload') ?> Upload a study</a>
<?php else: ?>
<div class="card card-elevated track-board">
        <div class="table-wrap">
            <table class="data-table paper-table" data-sortable>
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
                    <?php foreach ($waitingStudies as $study): ?>
                        <tr>
                            <td><a href="<?= e(url('studies/view', ['id' => $study['id']])) ?>"><?= e($study['title']) ?></a></td>
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
</div>
<?php endif; ?>
<?php elseif (!$hasAny): ?>
    <p class="empty-state"><?= $adviserTrack
        ? 'No studies yet. Submit an upload to send it to the dept research coordinator.'
        : 'No studies yet. Upload a study to wait for your research adviser, then track it here.' ?></p>
    <a class="btn btn-primary" href="<?= e(url('studies/create')) ?>"><?= icon('upload') ?> Upload a study</a>
<?php else: ?>
<?php foreach ($trackingGroups as $day => $papers): ?>
<section class="published-track-day">
    <h2 class="section-title"><?= $day !== '' ? e(date('F j, Y', strtotime($day))) : 'Date unavailable' ?></h2>
    <ul class="track-list">
    <?php foreach ($papers as $study): ?>
        <li>
            <p class="study-main">
                <a class="study-title" href="<?= e(url('studies/view', ['id' => $study['id']])) ?>"><?= e($study['title']) ?></a>
                <span class="badge"><?= e(study_type_label($study['study_type'] ?? '')) ?></span>
                <?= status_badge((string) $study['status'], $statusLabel($study['status'])) ?>
            </p>
            <p class="publication-timestamp"><?= $study['status'] === 'approved' ? 'Published: ' : 'Last update: ' ?><?= e($trackTime($study) !== '' ? publication_timestamp_label($trackTime($study)) : ($study['status'] === 'approved' ? 'Publication date unavailable' : 'Date unavailable')) ?></p>
            <?php partial('study_progress', ['study' => $study, 'skipAdviser' => $adviserTrack]); ?>
        </li>
    <?php endforeach; ?>
    </ul>
</section>
<?php endforeach; ?>
<?php endif; ?>
