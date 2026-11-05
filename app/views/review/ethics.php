<?php use App\Helpers\Auth; ?>
<dialog id="review-decision-modal" class="preview-modal" data-back="<?= e(url('review')) ?>" aria-labelledby="review-decision-heading">
    <div class="preview-modal-head">
        <h2 id="review-decision-heading">Review</h2>
        <button type="button" class="btn btn-secondary btn-icon-only" id="review-decision-close" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
    <?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
    <?php if (!$canApprove['ok'] && Auth::hasRole(['school_research_coordinator', 'school_administrator']) && $study['status'] !== 'approved'): ?>
        <p class="alert alert-error"><?= e($canApprove['reason']) ?></p>
    <?php endif; ?>
    <h3><?= e($study['title']) ?></h3>
    <p class="scholar-meta"><?= e(trim(($study['program_code'] ?? '') . ' · ' . ($study['school_year'] ?? ''), ' ·')) ?></p>

    <?php if (Auth::hasRole('research_adviser') && $study['status'] === 'submitted' && (int) $study['adviser_user_id'] === (int) Auth::user()['id']): ?>
        <h3>Approve</h3>
        <form id="adviser-approve" method="post" action="<?= e(url('review/finish')) ?>" class="stack">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
            <label for="adviser_note">Note for the student</label>
            <textarea id="adviser_note" name="adviser_note" rows="3" required>Okay, ready for the dept research coordinator.</textarea>
            <p class="muted">Approving does not forward the study. The student sends it to the dept research coordinator after you approve.</p>
        </form>
        <div class="actions">
            <button type="submit" class="btn btn-green" form="adviser-approve"><?= icon('check') ?> Approve</button>
            <form method="post" action="<?= e(url('review/request-revision')) ?>">
                <?= \App\Helpers\Csrf::field() ?>
                <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
                <button type="submit" class="btn btn-secondary"><?= icon('refresh') ?> Request revision</button>
            </form>
        </div>
    <?php elseif (Auth::hasRole('research_adviser') && $study['status'] === 'adviser_reviewed'): ?>
        <p class="muted">You approved this study. The student sends it to the dept research coordinator.</p>
    <?php endif; ?>

    <?php if (!empty($isDean) && $study['status'] === 'dean_review'): ?>
        <h3>Dean approval</h3>
        <p class="muted">Approve this study to send it to the school research coordinator.</p>
        <form method="post" action="<?= e(url('review/dean-approve')) ?>" class="actions">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
            <button type="submit" class="btn btn-green"><?= icon('check') ?> Approve for school review</button>
        </form>
    <?php endif; ?>

    <?php if (Auth::hasRole('school_research_coordinator') && $study['status'] === 'under_review'): ?>
        <h3>Ethics and manuscript format</h3>
        <form method="post" action="<?= e(url('review/ethics/submit')) ?>" class="stack" enctype="multipart/form-data">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
            <?php foreach ([
                'human_subjects' => 'Human subjects',
                'informed_consent' => 'Informed consent',
                'ethical_clearance' => 'Ethical clearance',
                'data_privacy_ra10173' => 'Data privacy',
                'coi_disclosed' => 'Conflict of interest disclosure',
                'manuscript_format' => 'Manuscript format',
            ] as $field => $label): ?>
                <label for="check-<?= e($field) ?>"><?= e($label) ?></label>
                <select id="check-<?= e($field) ?>" name="<?= e($field) ?>">
                    <?php foreach (['pending' => 'Pending', 'passed' => 'Passed', 'failed' => 'Failed'] as $value => $text): ?>
                        <option value="<?= e($value) ?>" <?= ($ethics[$field] ?? 'pending') === $value ? 'selected' : '' ?>><?= e($text) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $remarksKey = match ($field) {
                    'data_privacy_ra10173' => 'data_privacy_remarks',
                    'coi_disclosed' => 'coi_remarks',
                    default => $field . '_remarks',
                }; ?>
                <label for="remarks-<?= e($field) ?>"><?= e($label) ?> remarks (required if failed)</label>
                <textarea id="remarks-<?= e($field) ?>" name="<?= e($field . '_remarks') ?>" rows="2"><?= e($ethics[$remarksKey] ?? '') ?></textarea>
            <?php endforeach; ?>
            <label for="irb-code">Ethical clearance / IRB code</label>
            <input id="irb-code" name="irb_code" value="<?= e($ethics['irb_code'] ?? '') ?>">
            <p class="muted">A clearance code or existing clearance document is required to pass ethical clearance.</p>
            <button type="submit" class="btn btn-primary">Save checks</button>
        </form>
        <h3>Plagiarism result</h3>
        <p class="muted">Record the result for the current manuscript. Similarity must be below <?= e((string) \App\Helpers\Settings::getFloat('plagiarism_block_percent', DEFAULT_PLAGIARISM_BLOCK_PERCENT)) ?>% to advance.</p>
        <?php if ($plagiarism): ?><p>Current result: <?= e((string) $plagiarism['similarity_percentage']) ?>%</p><?php endif; ?>
        <form method="post" action="<?= e(url('review/plagiarism/submit')) ?>" class="stack" enctype="multipart/form-data">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
            <label for="similarity">Similarity percentage</label>
            <input id="similarity" type="number" name="similarity_percentage" min="0" max="100" step="0.01" required>
            <label for="similarity-shot">Result screenshot (optional)</label>
            <input id="similarity-shot" type="file" name="screenshot" accept="image/png,image/jpeg,image/webp">
            <button type="submit" class="btn btn-primary">Save plagiarism result</button>
        </form>
        <div class="actions">
            <form method="post" action="<?= e(url('review/approve')) ?>">
                <?= \App\Helpers\Csrf::field() ?>
                <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
                <button type="submit" class="btn btn-green" <?= !$canApprove['ok'] ? 'disabled' : '' ?>><?= icon('check') ?> Send to school administrator</button>
            </form>
            <form method="post" action="<?= e(url('review/reject')) ?>" data-confirm="Do you want to reject this study?">
                <?= \App\Helpers\Csrf::field() ?>
                <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
                <button type="submit" class="btn btn-danger"><?= icon('x') ?> Reject</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (Auth::hasRole('school_administrator') && in_array($study['status'], ['admin_review', 'library_review'], true)): ?>
        <form method="post" action="<?= e(url('review/final-publish')) ?>" class="actions">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
            <button type="submit" class="btn btn-green" <?= !$canApprove['ok'] ? 'disabled' : '' ?>><?= icon('check') ?> Publish</button>
        </form>
    <?php endif; ?>

    <?php if (Auth::hasRole(['school_research_coordinator', 'school_administrator']) && $study['status'] === 'approved'): ?>
        <p class="muted">This study is approved.</p>
    <?php endif; ?>
</dialog>
