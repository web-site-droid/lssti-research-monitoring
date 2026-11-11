<?php
use App\Helpers\Auth;
$pageTitle = e($study['short_title']);
?>
<div class="page-header">
    <h1><?= e($study['title']) ?></h1>
    <p class="meta">Program: <?= e($study['program_code']) ?> · SY: <?= e($study['school_year']) ?> · Type: <?= e(study_type_label($study['study_type'] ?? '')) ?></p>
</div>
<?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

<?php if (!in_array($study['status'], ['draft', 'submitted', 'resubmitted', 'revision_requested'], true)): ?>
<div class="card card-elevated">
    <?php partial('study_progress', [
        'study' => $study,
        'skipAdviser' => ($user['role_slug'] ?? '') === 'research_adviser' && (int) $study['submitter_user_id'] === (int) ($user['id'] ?? 0),
    ]); ?>
</div>
<?php endif; ?>

<p><strong>Authors:</strong> <?= e($authorDisplay) ?></p>
<p><strong>Upload date:</strong> <?= e(upload_date_label($study)) ?></p>
<p><strong>Recorded upload time:</strong> <?= e(timestamp_label($study['created_at'] ?? null)) ?></p>
<p><strong>Keywords:</strong> <?= e(implode(', ', $keywords)) ?></p>
<h2 class="section-title">Abstract</h2>
<p><?= nl2br(e($study['abstract'])) ?></p>
<?php if (trim((string) ($study['research_objectives'] ?? '')) !== ''): ?>
<h2 class="section-title">Research objectives</h2>
<p><?= nl2br(e($study['research_objectives'])) ?></p>
<?php endif; ?>

<?php if (!empty($fileMissing)): ?>
    <p class="alert alert-error">The manuscript file is missing. Upload the PDF or DOCX again.</p>
<?php elseif (can_open_full_file($user, $study)): ?>
    <p><a class="btn btn-primary" href="<?= e(url('reader', ['id' => $study['id']])) ?>"><?= icon('book') ?> Open full file</a></p>
<?php endif; ?>
<?php partial('replace_file', ['study' => $study, 'canReplaceFile' => $canReplaceFile ?? false]); ?>

<?php if (($user['role_slug'] ?? '') === 'student' && $study['status'] === 'draft' && (int) $study['submitter_user_id'] === (int) $user['id']): ?>
    <form method="post" action="<?= e(url('studies/submit')) ?>">
        <?= \App\Helpers\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $study['id'] ?>">
        <p class="muted">Your adviser checks this study before it goes to the dept research coordinator.</p>
        <button type="submit" class="btn btn-primary"><?= icon('send') ?> Pass for adviser</button>
    </form>
<?php endif; ?>
<?php if (($user['role_slug'] ?? '') === 'student' && $study['status'] === 'adviser_reviewed' && (int) $study['submitter_user_id'] === (int) $user['id']): ?>
    <form method="post" action="<?= e(url('studies/submit-librarian')) ?>">
        <?= \App\Helpers\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $study['id'] ?>">
        <p class="muted">Your research adviser approved this study. You send it to the dept research coordinator.</p>
        <button type="submit" class="btn btn-green"><?= icon('send') ?> Send to dept research coordinator</button>
    </form>
<?php endif; ?>
<?php if (($user['role_slug'] ?? '') === 'student' && $study['status'] === 'revision_requested' && (int) $study['submitter_user_id'] === (int) $user['id']): ?>
    <a class="btn btn-secondary" href="<?= e(url('studies/revise', ['id' => $study['id']])) ?>"><?= icon('upload') ?> Upload revision</a>
<?php endif; ?>

<?php if (!empty($scores)): ?>
<h2 class="section-title">Review scores</h2>
<ul class="list">
    <?php foreach ($scores as $sc): ?>
        <li><?= e($sc['first_name'] . ' ' . $sc['last_name']) ?>: <strong><?= e((string) $sc['score']) ?></strong>
            <?php if ($sc['remarks']): ?> — <?= e($sc['remarks']) ?><?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if (Auth::hasRole('research_adviser')): ?>
<div class="actions">
    <a class="btn btn-primary" href="<?= e(url('review/ethics', ['id' => $study['id']])) ?>"><?= icon('clipboard') ?> Faculty review workspace</a>
</div>
<?php endif; ?>
<?php if (!empty($showReaders)): ?>
<section class="card card-elevated" style="margin-top:1.25rem">
    <h2 class="section-title">Who read this article</h2>
    <p class="muted">People in any department who opened the full file.</p>
    <?php partial('readers_table', ['readers' => $readers ?? []]); ?>
</section>
<section class="card card-elevated" style="margin-top:1.25rem">
    <h2 class="section-title">Who downloaded this file</h2>
    <p class="muted">People in any department who downloaded the full file.</p>
    <?php partial('readers_table', [
        'readers' => $downloaders ?? [],
        'emptyText' => 'No one has downloaded this file yet.',
        'timeLabel' => 'Downloaded',
    ]); ?>
</section>
<?php endif; ?>
<?php if (!empty($canReplaceFile)): ?>
    <?php $pageScripts = ['manuscript-upload.js']; ?>
<?php endif; ?>
