<?php
$old = $old ?? form_old();
$editingId = (int) ($editingId ?? 0);
$uploader = \App\Helpers\Auth::user();
$isAdviser = ($uploader['role_slug'] ?? '') === 'research_adviser';
$authorFirst = $old['author_first'] ?? [''];
$authorMiddle = $old['author_middle'] ?? [''];
$authorLast = $old['author_last'] ?? [''];
if ($authorFirst === []) {
    $authorFirst = [''];
}
$pageTitle = $editingId ? 'Edit draft' : 'Upload a study';
?>
<div class="upload-page">
    <div class="page-header">
        <h1><?= $editingId ? 'Edit draft' : 'Upload a study' ?></h1>
        <p class="lead"><?= $editingId ? 'Change this draft, then save or send it.' : 'Your research adviser checks this study first. Then you send it to the dept research coordinator.' ?></p>
    </div>
    <?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="<?= e(url($editingId ? 'studies/edit/submit' : 'studies/create/submit')) ?>" enctype="multipart/form-data" class="upload-layout" id="study-form">
        <?= \App\Helpers\Csrf::field() ?>
        <?php if ($editingId): ?>
            <input type="hidden" name="id" value="<?= $editingId ?>">
        <?php endif; ?>
        <?php if ($isAdviser): ?>
            <input type="hidden" name="pass_to" value="library">
        <?php else: ?>
            <input type="hidden" name="pass_to" value="teacher">
        <?php endif; ?>
        <input type="hidden" name="intent" id="intent" value="draft">
        <section class="card card-elevated upload-card upload-one">
            <div class="upload-fields">
                <div class="profile-field">
                    <label for="title">Manuscript title <span class="req">*</span></label>
                    <textarea id="title" name="title" rows="2" maxlength="512" required><?= e($old['title'] ?? '') ?></textarea>
                </div>
                <div class="profile-field">
                    <label for="short_title">Short title <span class="req">*</span> <span class="field-count"><span id="short-count">0</span>/80</span></label>
                    <input id="short_title" name="short_title" maxlength="80" required value="<?= e($old['short_title'] ?? '') ?>">
                </div>
                <div class="profile-field">
                    <label for="abstract">Abstract <span class="req">*</span> <span class="field-count"><span id="word-count">0</span>/350 words</span></label>
                    <textarea id="abstract" name="abstract" rows="6" required><?= e($old['abstract'] ?? '') ?></textarea>
                </div>
                <div class="profile-field">
                    <label for="keywords">Keywords</label>
                    <input id="keywords" name="keywords" placeholder="keyword1, keyword2, ..." value="<?= e($old['keywords'] ?? '') ?>">
                    <p class="muted">Separate keywords with commas. Any number is fine.</p>
                </div>
                <div class="profile-field">
                    <label>Authors <span class="req">*</span></label>
                    <p class="muted">Add every member. A middle initial such as S. is fine.</p>
                    <div id="author-list">
                    <?php foreach ($authorFirst as $i => $first): ?>
                    <div class="author-row">
                        <input name="author_first[]" placeholder="First" required value="<?= e((string) $first) ?>" aria-label="First name">
                        <input class="author-middle" name="author_middle[]" placeholder="M.I." maxlength="4" value="<?= e((string) ($authorMiddle[$i] ?? '')) ?>" aria-label="Middle initial">
                        <input name="author_last[]" placeholder="Last" required value="<?= e((string) ($authorLast[$i] ?? '')) ?>" aria-label="Last name">
                        <button type="button" class="btn btn-secondary btn-icon-only remove-author" aria-label="Remove author"<?= count($authorFirst) < 2 ? ' hidden' : '' ?>><?= icon('x') ?></button>
                    </div>
                    <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary" id="add-author"><?= icon('plus') ?> Add author</button>
                </div>
                <?php $lockedProgramId = (int) ($lockedProgramId ?? ($uploader['program_id'] ?? 0)); ?>
                <input type="hidden" id="program_id" name="program_id" value="<?= $lockedProgramId ?>">
                <div class="upload-pair">
                    <div class="profile-field">
                        <label for="study_type">Study type <span class="req">*</span></label>
                        <select id="study_type" name="study_type" required>
                            <?php foreach (study_type_options() as $typeValue => $typeLabel): ?>
                                <option value="<?= e($typeValue) ?>" <?= ($old['study_type'] ?? 'thesis') === $typeValue ? 'selected' : '' ?>><?= e($typeLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="profile-field">
                        <label for="school_year">School year <span class="req">*</span></label>
                        <select id="school_year" name="school_year" required>
                            <option value="">Choose a school year</option>
                            <?php foreach ($schoolYears as $year): ?>
                                <option value="<?= e($year) ?>" <?= ($old['school_year'] ?? '') === $year ? 'selected' : '' ?>><?= e($year) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="profile-field" id="adviser-field"<?= $isAdviser ? ' hidden' : '' ?>>
                    <label for="adviser_user_id">Adviser <span class="req">*</span></label>
                    <select id="adviser_user_id" name="adviser_user_id"<?= $isAdviser ? '' : ' required' ?>>
                        <option value="">Choose a research adviser</option>
                        <?php foreach ($advisers as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" data-program="<?= (int) ($a['program_id'] ?? 0) ?>" <?= (string) ($old['adviser_user_id'] ?? '') === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['last_name'] . ', ' . $a['first_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php $manuscriptInvalid = !empty($error) && stripos((string) $error, 'Manuscript:') !== false; ?>
                <div class="profile-field file-field<?= $manuscriptInvalid ? ' field-invalid' : '' ?>">
                    <label for="manuscript">Manuscript<?= $editingId ? '' : ' <span class="req">*</span>' ?></label>
                    <input type="file" id="manuscript" name="manuscript" accept=".pdf,.docx,application/pdf" <?= $editingId ? '' : 'required' ?><?= $manuscriptInvalid ? ' aria-invalid="true"' : '' ?>>
                    <?php if ($manuscriptInvalid): ?><p class="field-error">Manuscript needs a correction. Choose the file again.</p><?php endif; ?>
                    <p class="muted">PDF or DOCX. Readers see the full study.<?php if ($editingId): ?> Leave the file empty to keep the copy already saved.<?php else: ?> If a message appears, your answers stay in the form. Choose the file again.<?php endif; ?></p>
                </div>
            </div>
            <div class="upload-actions">
                <?php if ($editingId): ?>
                    <a class="btn profile-cancel" href="<?= e(url('profile')) ?>"><?= icon('arrow-left') ?> Back</a>
                    <button type="submit" class="btn profile-cancel" id="save-draft"><?= icon('save') ?> Save changes</button>
                    <button type="submit" class="btn profile-save" id="submit-review"><?= icon('send') ?> Submit</button>
                <?php else: ?>
                    <button type="submit" class="btn profile-cancel" id="save-draft"><?= icon('save') ?> Save as draft</button>
                    <button type="submit" class="btn profile-save" id="submit-review"><?= icon('send') ?> <span id="submit-label"><?= $isAdviser ? 'Submit' : 'Pass to adviser' ?></span></button>
                <?php endif; ?>
            </div>
        </section>
    </form>
</div>
<?php $pageScripts = ['study-form.js']; ?>
