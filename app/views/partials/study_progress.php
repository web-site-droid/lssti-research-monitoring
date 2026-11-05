<?php
use App\Helpers\Workflow;
$status = $study['status'] ?? 'draft';
$skipAdviser = !empty($skipAdviser);
$step = Workflow::studyProgressStep($status, $skipAdviser);
$labels = Workflow::progressLabels($skipAdviser);
$detail = match ($status) {
    'approved' => 'The librarian published this study.',
    'admin_review' => 'Waiting for the librarian to publish.',
    'library_review' => 'Waiting for the librarian to publish.',
    'under_review' => 'With the school research coordinator for ethics and plagiarism checks.',
    'dean_review' => 'Waiting for dean approval.',
    'pending' => 'Waiting for the dept research coordinator to review and send it to the dean.',
    'adviser_reviewed' => 'Your research adviser approved this study. You send it to the dept research coordinator.',
    'submitted' => 'With your research adviser.',
    'archived' => 'This study was not approved.',
    'draft' => $skipAdviser
        ? 'Finish the upload, then submit it to the dept research coordinator.'
        : 'Finish the upload, then submit it to your research adviser.',
    'revision_requested' => 'Changes were requested. Upload a revision, then it returns to your research adviser.',
    default => 'Pipeline: adviser → dept coordinator → dean → research coordinator → librarian.',
};
?>
<ol class="progress-track" aria-label="Study progress">
    <?php foreach ($labels as $i => $label): ?>
        <?php $n = $i + 1; $active = $n <= $step; ?>
        <li class="progress-step <?= $active ? 'is-active' : '' ?> <?= $n === $step ? 'is-current' : '' ?>">
            <div class="progress-rail">
                <span class="progress-dot"><?= $n ?></span>
                <?php if ($n < count($labels)): ?><span class="progress-line <?= $n < $step ? 'is-active' : '' ?>"></span><?php endif; ?>
            </div>
            <span class="progress-label"><?= e($label) ?></span>
        </li>
    <?php endforeach; ?>
</ol>
<p class="status-line">Now: <?= status_badge((string) $status, (string) ($labels[$step - 1] ?? $labels[0])) ?></p>
<p class="muted"><?= e($detail) ?></p>
