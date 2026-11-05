<dialog id="library-review-modal" class="preview-modal profile-modal" data-back="<?= e(url((\App\Helpers\Auth::user()['role_slug'] ?? '') === 'dept_research_coordinator' ? 'library' : 'dashboard')) ?>" aria-labelledby="library-review-heading">
<div class="preview-modal-head">
    <h2 id="library-review-heading"><?= e($study['title']) ?></h2>
    <button type="button" class="btn btn-secondary btn-icon-only" id="library-review-close" aria-label="Close"><?= icon('x') ?></button>
</div>
<p class="scholar-meta"><?= e($authorDisplay) ?> · <?= e($study['program_code']) ?> · <?= e($study['school_year']) ?></p>
<?php if (!empty($success)): ?><p class="alert alert-success"<?= (($_GET['saved'] ?? '') === 'note') ? ' data-swal="note"' : '' ?>><?= e($success) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

<section class="card">
    <h2 class="section-title">Study</h2>
    <?php
    $isLibrarianGate = ($study['status'] ?? '') === 'library_review' || !empty($isLibrarianGate);
    $canSend = in_array($study['status'] ?? '', ['pending', 'library_review'], true)
        && \App\Models\Study::hasLibraryReview((int) $study['id']);
    ?>
    <p><?= status_badge((string) ($study['status'] ?? ''), match ($study['status'] ?? '') {
        'pending' => 'With dept research coordinator',
        'dean_review' => 'With dean',
        'under_review' => 'With school research coordinator',
        'library_review' => 'With librarian',
        'admin_review' => 'With librarian',
        default => ucwords(str_replace('_', ' ', (string) ($study['status'] ?? ''))),
    }) ?></p>
    <h3>Abstract</h3>
    <p><?= nl2br(e($study['abstract'])) ?></p>
    <?php if (trim((string) ($study['research_objectives'] ?? '')) !== ''): ?>
    <h3>Research objectives</h3>
    <p><?= nl2br(e($study['research_objectives'])) ?></p>
    <?php endif; ?>
</section>

<section class="card">
    <h2 class="section-title">Study details</h2>
    <p class="muted">Set the document type, year, abstract, and keywords that readers see.</p>
    <form method="post" action="<?= e(url('library/metadata')) ?>" class="stack">
        <?= \App\Helpers\Csrf::field() ?>
        <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
        <label for="study_type">Document type</label>
        <select id="study_type" name="study_type" required>
            <?php foreach (study_type_options() as $typeValue => $typeLabel): ?>
                <option value="<?= e($typeValue) ?>" <?= ($study['study_type'] ?? '') === $typeValue ? 'selected' : '' ?>><?= e($typeLabel) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="school_year">School year</label>
        <input id="school_year" name="school_year" required value="<?= e($study['school_year']) ?>">
        <label for="abstract">Abstract</label>
        <textarea id="abstract" name="abstract" rows="5" required><?= e($study['abstract']) ?></textarea>
        <label for="keywords">Keywords</label>
        <input id="keywords" name="keywords" value="<?= e(implode(', ', \App\Models\Study::keywords((int) $study['id']))) ?>">
        <button type="submit" class="btn btn-secondary">Save study details</button>
    </form>
</section>

<section class="card">
    <h2 class="section-title">Library comment</h2>
    <form method="post" action="<?= e(url('library/comment')) ?>" class="stack">
        <?= \App\Helpers\Csrf::field() ?>
        <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
        <label for="comment">Note</label>
        <textarea id="comment" name="comment" rows="4" required placeholder="<?= $isLibrarianGate ? 'Okay. Ready for librarian.' : 'Okay. Ready for the dean.' ?>"><?= $isLibrarianGate ? 'Okay. Ready for librarian.' : 'Okay. Ready for the dean.' ?></textarea>
        <button type="submit" class="btn btn-primary"><?= icon('save') ?> Save note</button>
    </form>
    <?php if ($canSend): ?>
        <form method="post" action="<?= e(url('library/approve')) ?>" class="stack">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
            <p class="muted"><?= $isLibrarianGate
                ? 'The note is saved. Submit this study to the librarian.'
                : 'The note is saved. Submit this study to the dean.' ?></p>
            <button type="submit" class="btn btn-primary"><?= icon('send') ?> <?= $isLibrarianGate ? 'Submit to librarian' : 'Submit to dean' ?></button>
        </form>
    <?php elseif (($study['status'] ?? '') === 'pending'): ?>
        <p class="muted">Save an okay note before you submit this study to the dean.</p>
    <?php elseif (($study['status'] ?? '') === 'library_review'): ?>
        <p class="muted">Save an okay note before you submit this study to the librarian.</p>
    <?php elseif (($study['status'] ?? '') === 'admin_review'): ?>
        <p class="muted">Submitted to the librarian for publish.</p>
    <?php endif; ?>
    <button type="button" class="btn btn-secondary" id="review-notes-toggle" aria-haspopup="dialog" aria-controls="review-notes">Notes</button>
    <dialog id="review-notes" class="preview-modal" aria-labelledby="review-notes-heading">
        <div class="preview-modal-head">
            <h2 id="review-notes-heading">Notes</h2>
            <button type="button" class="btn btn-secondary btn-icon-only" id="review-notes-close" aria-label="Close"><?= icon('x') ?></button>
        </div>
        <?php if ($comments): ?>
            <ul class="list">
            <?php foreach ($comments as $c): ?>
                <li>
                    <strong><?= e($c['first_name'] . ' ' . $c['last_name']) ?></strong>
                    <span class="text-small"><?= e($c['created_at']) ?></span>
                    <p><?= nl2br(e($c['comment_text'])) ?></p>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">No notes yet.</p>
        <?php endif; ?>
    </dialog>
</section>

<?php if ($study['status'] === 'approved'): ?>
<section class="card">
    <h2 class="section-title">Catalog</h2>
    <div class="actions">
        <?php if (empty($study['library_accepted_at']) || !empty($study['library_retracted_at'])): ?>
        <form method="post" action="<?= e(url('library/accept')) ?>" class="inline-form">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
            <button type="submit" class="btn btn-primary"><?= icon('book') ?> Accept into library</button>
        </form>
        <?php endif; ?>
        <?php if (empty($study['library_retracted_at'])): ?>
        <form method="post" action="<?= e(url('library/retract')) ?>" class="inline-form" data-confirm="Do you want to take this study out of the library?">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
            <button type="submit" class="btn btn-danger"><?= icon('x') ?> Retract</button>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
</dialog>
