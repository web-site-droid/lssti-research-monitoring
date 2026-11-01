<?php if (!empty($canReplaceFile)): ?>
<section class="card card-elevated" style="margin-top:1rem">
    <h2 class="section-title">Replace missing manuscript</h2>
    <p class="muted">The saved file is missing. Upload the PDF or DOCX again (up to <?= (int) \App\Helpers\FileUpload::maxMb() ?> MB).</p>
    <form method="post" action="<?= e(url('studies/replace-file')) ?>" enctype="multipart/form-data" class="stack" data-chunk-url="<?= e(url('studies/upload-part')) ?>" id="replace-file-form">
        <?= \App\Helpers\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $study['id'] ?>">
        <input type="hidden" name="manuscript_stored" value="">
        <input type="hidden" name="manuscript_name" value="">
        <div class="profile-field file-field">
            <label for="manuscript">Manuscript <span class="req">*</span></label>
            <input type="file" id="manuscript" name="manuscript" accept=".pdf,.docx,application/pdf" required>
        </div>
        <p class="upload-progress muted" hidden></p>
        <button type="submit" class="btn btn-primary"><?= icon('upload') ?> Save file</button>
    </form>
</section>
<?php endif; ?>
