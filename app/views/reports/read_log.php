<div class="page-header">
    <h1>Who read and downloaded this article<?php if ($study): ?>: <?= e($study['title'] ?: $study['short_title']) ?><?php endif; ?></h1>
    <p class="lead"><?= $study
        ? 'People who opened or downloaded the full file for this ' . e((string) ($study['program_code'] ?? '')) . ' article.'
        : 'Choose a study to see who opened or downloaded it.' ?></p>
</div>
<?php if ($study): ?>
    <section class="card card-elevated paper-group is-wide">
        <h2 class="section-title">Who read this article</h2>
        <?php partial('readers_table', [
            'readers' => $readers ?? [],
            'emptyText' => 'No one has opened this article yet.',
        ]); ?>
    </section>
    <section class="card card-elevated paper-group is-wide" style="margin-top:1.25rem">
        <h2 class="section-title">Who downloaded this file</h2>
        <?php partial('readers_table', [
            'readers' => $downloaders ?? [],
            'emptyText' => 'No one has downloaded this file yet.',
            'timeLabel' => 'Downloaded',
        ]); ?>
    </section>
    <p class="muted" style="margin-top:1rem"><a href="<?= e(url('reports')) ?>"><?= icon('arrow-left') ?> Back to Logs</a></p>
<?php else: ?>
    <p class="empty-state">Open Logs and choose an article title to see who read or downloaded it.</p>
<?php endif; ?>
