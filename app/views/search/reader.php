<?php
$pageTitle = 'Reader — ' . $study['short_title'];
$fullAccess = !empty($fullAccess) && !empty($version);
$fileUrl = $fullAccess ? url('file/manuscript', [
    'study_id' => $study['id'],
    'version_id' => $version['id'],
    'inline' => '1',
]) : '';
$downloadUrl = $fullAccess ? url('file/manuscript', [
    'study_id' => $study['id'],
    'version_id' => $version['id'],
]) : '';
?>
<header class="reader-head">
    <h1><?= e($study['title']) ?></h1>
    <p class="reader-byline"><?= e($authorDisplay) ?> · <?= e($study['program_code'] ?? '') ?> · <?= e($study['school_year']) ?> · <?= e(study_type_label($study['study_type'] ?? '')) ?></p>
    <?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
    <?php if (!empty($fileMissing)): ?><p class="alert alert-error">The manuscript file is missing. Upload the PDF or DOCX again.</p><?php endif; ?>
    <?php if (!$fullAccess): ?>
        <p class="alert alert-warn"><?php
            $readerRole = $user['role_slug'] ?? '';
            echo match ($readerRole) {
                'student' => 'Abstract only. Studies from other departments show the title and abstract.',
                'research_adviser' => 'Abstract only. The full manuscript is limited to your department.',
                'dean', 'dept_research_coordinator' => 'Abstract only. The full manuscript is limited to your department.',
                default => 'Abstract only. The full manuscript is limited to students in the ' . e($study['program_code'] ?? 'owning') . ' department.',
            };
        ?></p>
    <?php endif; ?>
</header>
<div class="reader-layout">
    <div class="reader-main">
        <section class="card reader-abstract" aria-label="Abstract">
            <h2>Abstract</h2>
            <div id="abstract-text"><?= nl2br(e($study['abstract'] ?? '')) ?></div>
            <h2>Keywords</h2>
            <p id="keyword-text"><?= e($keywords ? implode(', ', $keywords) : 'None listed.') ?></p>
            <?php if (!empty($study['research_objectives'])): ?>
                <h2>Research objectives</h2>
                <p id="objectives-text"><?= nl2br(e($study['research_objectives'])) ?></p>
            <?php endif; ?>
        </section>
        <section class="card reader-play" id="reader-speech" aria-label="Play" data-ext="<?= e($ext) ?>" data-file-url="<?= e($fileUrl) ?>" data-pdf-worker="<?= e(asset_url('vendor/pdfjs/build/pdf.worker.js')) ?>">
            <h2>Play</h2>
            <p id="tts-status" class="muted"><?= $fullAccess ? 'Loading the whole paper…' : 'Press Play to hear the abstract.' ?></p>
            <div class="tts-actions">
                <button type="button" id="tts-play" class="btn btn-secondary"><?= icon('play') ?> Play</button>
                <button type="button" id="tts-pause" class="btn btn-secondary"><?= icon('pause') ?> Pause</button>
                <button type="button" id="tts-stop" class="btn btn-secondary"><?= icon('stop') ?> Stop</button>
            </div>
            <div class="tts-settings">
                <label for="tts-rate">Speed <span id="tts-rate-value">1.0</span></label>
                <input type="range" id="tts-rate" min="0.5" max="2" step="0.1" value="1">
                <label for="tts-voice">Voice</label>
                <select id="tts-voice"></select>
            </div>
        </section>
    </div>
</div>
<?php if (!empty($showReaders)): ?>
<section class="card card-elevated" style="margin-top:1.25rem" aria-label="Who read this article">
    <h2 class="section-title">Who read this article</h2>
    <p class="muted">People who opened the full file of this <?= e((string) ($study['program_code'] ?? '')) ?> article.</p>
    <?php partial('readers_table', ['readers' => $readers ?? []]); ?>
</section>
<section class="card card-elevated" style="margin-top:1.25rem" aria-label="Who downloaded this file">
    <h2 class="section-title">Who downloaded this file</h2>
    <p class="muted">People who downloaded the full file of this <?= e((string) ($study['program_code'] ?? '')) ?> article.</p>
    <?php partial('readers_table', [
        'readers' => $downloaders ?? [],
        'emptyText' => 'No one has downloaded this file yet.',
        'timeLabel' => 'Downloaded',
    ]); ?>
</section>
<?php endif; ?>
<?php partial('replace_file', ['study' => $study, 'canReplaceFile' => $canReplaceFile ?? false]); ?>
<?php if ($fullAccess): ?>
<section class="doc-viewer" aria-label="Manuscript">
    <div class="doc-toolbar">
        <p class="muted">The whole file is shown here. Play reads it aloud.</p>
        <a class="doc-download" href="<?= e($downloadUrl) ?>"><?= icon('download') ?> Download</a>
    </div>
    <div class="doc-body">
        <nav id="page-nav" class="doc-pages" aria-label="Pages" hidden></nav>
        <div class="doc-sheet">
            <div id="pdf-viewer" class="pdf-viewer"></div>
            <div id="docx-preview" class="docx-html" hidden></div>
        </div>
    </div>
</section>
<article id="reader-content" hidden></article>
<link rel="stylesheet" href="<?= e(asset_url('vendor/pdfjs/web/pdf_viewer.css')) ?>">
<script src="<?= e(asset_url('vendor/mammoth/mammoth.browser.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/pdfjs/build/pdf.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/pdfjs/web/pdf_viewer.js')) ?>"></script>
<?php $pageScripts = !empty($canReplaceFile) ? ['manuscript-upload.js', 'reader.js'] : ['reader.js']; ?>
<?php elseif (!empty($fileMissing)): ?>
<?php $pageScripts = !empty($canReplaceFile) ? ['manuscript-upload.js', 'reader.js'] : ['reader.js']; ?>
<?php else: ?>
<section class="card" aria-label="Access notice">
    <h2>Full paper unavailable</h2>
    <p class="muted">You can browse this study’s abstract. Open the full file only if it belongs to your department.</p>
</section>
<?php $pageScripts = ['reader.js']; ?>
<?php endif; ?>
