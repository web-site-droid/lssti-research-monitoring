<h1>Upload revision</h1>
<p><?= e($study['title']) ?></p>
<?php $old = form_old(); ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="<?= e(url('studies/revise/submit')) ?>" enctype="multipart/form-data" class="stack">
    <?= \App\Helpers\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) $study['id'] ?>">
    <label for="change_note">Change note</label>
    <textarea id="change_note" name="change_note" required><?= e($old['change_note'] ?? '') ?></textarea>
    <?php $manuscriptInvalid = !empty($error) && stripos((string) $error, 'Manuscript:') !== false; ?>
    <div class="<?= $manuscriptInvalid ? 'field-invalid' : '' ?>">
        <label for="manuscript">Revised manuscript (PDF or DOCX)</label>
        <input type="file" id="manuscript" name="manuscript" accept=".pdf,.docx" required<?= $manuscriptInvalid ? ' aria-invalid="true"' : '' ?>>
        <?php if ($manuscriptInvalid): ?><p class="field-error">Manuscript needs a correction. Choose the file again.</p><?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('upload') ?> Upload and resubmit</button>
</form>
