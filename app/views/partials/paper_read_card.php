<?php
$canRead = can_open_full_file(\App\Helpers\Auth::user(), $paper);
$title = (string) ($paper['title'] ?? $paper['short_title'] ?? '');
$authors = (string) ($paper['authors'] ?? '');
if ($authors === '' && !empty($paper['last_name'])) {
    $authors = trim(($paper['last_name'] ?? '') . ', ' . ($paper['first_name'] ?? ''), ', ');
}
$meta = trim(
    ($paper['program_code'] ?? '') . ' · ' . ($paper['school_year'] ?? '') . ' · ' . study_type_label($paper['study_type'] ?? ''),
    ' ·'
);
?>
<article class="paper-card<?= $canRead ? ' is-readable' : '' ?>"<?= paper_find_attrs($paper) ?><?= isset($gate) ? ' data-gate="' . e((string) $gate) . '"' : '' ?><?= isset($decision) ? ' data-decision="' . e((string) $decision) . '"' : '' ?> data-status="<?= e((string) ($paper['status'] ?? '')) ?>">
    <?php if ($canRead): ?>
    <a class="paper-card-open" href="<?= e(url('reader', ['id' => $paper['id']])) ?>">
    <?php endif; ?>
        <h3><?= e($title) ?></h3>
        <p class="paper-card-authors"><?= author_cell($authors, $title) ?></p>
        <p class="paper-card-meta"><?= e($meta) ?></p>
        <?php if ($canRead): ?><span class="paper-card-hint">Open the whole file</span><?php endif; ?>
    <?php if ($canRead): ?>
    </a>
    <?php endif; ?>
</article>
