<?php
$note = $note ?? [];
$href = '';
if (!empty($note['link_route'])) {
    $parts = explode('?', (string) $note['link_route'], 2);
    parse_str($parts[1] ?? '', $q);
    $href = url($parts[0], $q);
}
?>
<?php $unread = (int) ($note['is_read'] ?? 0) === 0; ?>
<button type="button" class="notify-item<?= $unread ? ' is-unread' : '' ?>" data-notify-open
    data-id="<?= (int) ($note['id'] ?? 0) ?>"
    data-read-url="<?= e(url('notifications/read', ['id' => (int) ($note['id'] ?? 0), 'ajax' => 1])) ?>"
    data-title="<?= e($note['title'] ?? '') ?>"
    data-body="<?= e($note['body'] ?? '') ?>"
    data-time="<?= e(timestamp_label($note['created_at'] ?? null)) ?>"
    data-href="<?= e($href) ?>">
    <span class="notify-dot" aria-hidden="true"></span>
    <strong><?= e($note['title'] ?? '') ?></strong>
    <time class="notify-time"><?= e(timestamp_label($note['created_at'] ?? null)) ?></time>
    <?php if (!empty($note['body'])): ?><span class="notify-body"><?= e($note['body']) ?></span><?php endif; ?>
    <span class="notify-state" data-notify-state><?= $unread ? 'Unread' : 'Read' ?></span>
</button>
