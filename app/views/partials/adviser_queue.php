<?php
$assigned = \App\Models\Study::listForUser($user);
$pending = array_values(array_filter($assigned, static fn (array $paper): bool =>
    in_array($paper['status'] ?? '', ['submitted', 'revision_requested'], true)
));
?>
<section class="adviser-priority" id="adviser-priority" aria-labelledby="adviser-priority-title">
    <div class="adviser-priority-heading">
        <div>
            <p class="adviser-eyebrow">Your review workspace</p>
            <h2 id="adviser-priority-title">Needs your attention <span><?= count($pending) ?></span></h2>
            <p class="muted">Assigned manuscripts awaiting your review or a revised submission.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('review')) ?>">Open all reviews <?= icon('arrow-right') ?></a>
    </div>
    <?php if (!$pending): ?>
        <p class="adviser-queue-empty">You're up to date. New assigned submissions will appear here.</p>
    <?php else: ?>
        <div class="adviser-queue">
        <?php foreach (array_slice($pending, 0, 5) as $paper): ?>
            <article class="adviser-queue-item">
                <div>
                    <?= status_badge($paper['status'], $paper['status'] === 'submitted' ? 'Ready for review' : 'Awaiting student revision') ?>
                    <h3><a href="<?= e(url('studies/view', ['id' => $paper['id']])) ?>"><?= e($paper['title']) ?></a></h3>
                    <p><?= e(trim(($paper['submitter_first'] ?? '') . ' ' . ($paper['submitter_last'] ?? ''))) ?> &middot; <?= e($paper['school_year'] ?? '') ?></p>
                    <p><?php partial('upload_timestamp', ['paper' => $paper]); ?></p>
                </div>
                <?php if ($paper['status'] === 'submitted'): ?>
                    <a class="btn btn-secondary" href="<?= e(url('review/ethics', ['id' => $paper['id']])) ?>">Review study</a>
                <?php else: ?>
                    <a class="btn btn-secondary" href="<?= e(url('studies/view', ['id' => $paper['id']])) ?>">View progress</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
        <?php if (count($pending) > 5): ?><p class="muted">Showing 5 of <?= count($pending) ?> assigned papers. Open all reviews to see the rest.</p><?php endif; ?>
    <?php endif; ?>
</section>
