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
    if ($study['status'] !== 'approved') {
        $tracking[] = $study;
    }
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
        'admin_review' => 'With school administrator',
        'archived' => 'Not approved',
        default => 'In review',
    };
};
$inReview = [];
$approved = [];
foreach ($tableStudies as $study) {
    if (($study['status'] ?? '') === 'approved') {
        $approved[] = $study;
    } else {
        $inReview[] = $study;
    }
}
$hasAny = $tableStudies !== [] || $waitingStudies !== [];
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
<?php if ($tracking !== []): ?>
<ul class="track-list">
    <?php foreach ($tracking as $study): ?>
        <li>
            <p class="study-main">
                <a class="study-title" href="<?= e(url('studies/view', ['id' => $study['id']])) ?>"><?= e($study['short_title']) ?></a>
                <span class="badge"><?= e(study_type_label($study['study_type'] ?? '')) ?></span>
                <?= status_badge((string) ($study['status'] ?? ''), $statusLabel($study['status'])) ?>
            </p>
            <?php partial('study_progress', ['study' => $study, 'skipAdviser' => $adviserTrack]); ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
<div class="track-fuse" role="group" aria-label="Study lists">
    <button type="button" class="is-on" data-track-panel="review" aria-pressed="true">In review</button>
    <button type="button" data-track-panel="approved" aria-pressed="false">Approved</button>
</div>
<div class="card card-elevated track-board">
<div class="paper-tables track-panels">
    <section class="paper-group" data-panel="review">
        <?php if ($inReview === []): ?>
            <p class="empty-state">No studies in review.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table paper-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inReview as $study): ?>
                        <tr>
                            <td><a href="<?= e(url('studies/view', ['id' => $study['id']])) ?>"><?= e($study['short_title']) ?></a></td>
                            <td><?= e(study_type_label($study['study_type'] ?? '')) ?></td>
                            <td><?= e($statusLabel($study['status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
    <section class="paper-group" data-panel="approved" hidden>
        <h2 class="visually-hidden">Approved</h2>
        <?php if ($approved === []): ?>
            <p class="empty-state">No approved studies yet.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table paper-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approved as $study): ?>
                        <tr>
                            <td><a href="<?= e(url('studies/view', ['id' => $study['id']])) ?>"><?= e($study['short_title']) ?></a></td>
                            <td><?= e(study_type_label($study['study_type'] ?? '')) ?></td>
                            <td><?= e($statusLabel($study['status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
</div>
<script>
(function () {
  document.querySelectorAll('[data-track-panel]').forEach(function (button) {
    button.addEventListener('click', function () {
      var name = button.getAttribute('data-track-panel');
      document.querySelectorAll('[data-track-panel]').forEach(function (other) {
        var on = other === button;
        other.classList.toggle('is-on', on);
        other.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      document.querySelectorAll('[data-panel]').forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-panel') !== name;
      });
    });
  });
})();
</script>
<?php endif; ?>
