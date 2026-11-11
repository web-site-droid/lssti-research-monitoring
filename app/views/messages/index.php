<div class="page-header">
    <h1>Messages</h1>
    <p class="lead">Review comments on studies.</p>
</div>
<?php if (!$items): ?>
    <p class="empty-state">No messages yet.</p>
<?php else: ?>
    <ul class="list">
        <?php foreach ($items as $item): ?>
            <li>
                <strong><?= e($item['first_name'] . ' ' . $item['last_name']) ?></strong>
                <span class="text-small"><?= e($item['section_label'] ?? 'Comment') ?> · <?= e(timestamp_label($item['created_at'])) ?></span>
                <p><?= nl2br(e($item['comment_text'])) ?></p>
                <a href="<?= e(url('studies/view', ['id' => $item['study_id']])) ?>"><?= e($item['short_title']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>