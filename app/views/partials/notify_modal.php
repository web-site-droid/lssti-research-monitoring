<?php
$notes = $notes ?? [];
$unreadTotal = $unreadTotal ?? 0;
?>
<dialog id="notify-modal" class="preview-modal notify-modal" aria-labelledby="notify-modal-heading">
    <div class="preview-modal-head">
        <h2 id="notify-modal-heading">Alerts</h2>
        <div class="notify-modal-actions">
            <a class="btn btn-green btn-sm" id="notify-mark-all" href="<?= e(url('notifications/read-all')) ?>"><?= icon('check') ?> Mark all read</a>
            <button type="button" class="btn btn-secondary btn-icon-only" id="notify-modal-close" aria-label="Close"><?= icon('x') ?></button>
        </div>
    </div>
    <div id="notify-list">
    <?php if (!$notes): ?>
        <p class="empty-state">No alerts yet.</p>
    <?php else: ?>
        <ul class="list notify-modal-list">
            <?php foreach ($notes as $note): ?>
                <li><?php partial('notify_item', ['note' => $note]); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    </div>
    <div id="notify-detail" hidden>
        <div class="notify-detail-top">
            <h3 id="notify-detail-title"></h3>
            <time class="notify-time" id="notify-detail-time"></time>
        </div>
        <p id="notify-detail-body"></p>
        <p class="notify-detail-open"><a class="btn btn-primary btn-sm" id="notify-detail-link" href="#" hidden>Open</a></p>
    </div>
</dialog>
