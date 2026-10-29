<div class="page-header notice-head">
    <h1>Alerts</h1>
    <a class="btn btn-green" href="<?= e(url('notifications/read-all')) ?>"><?= icon('check') ?> Mark all read</a>
</div>
<ul class="list notice-list">
    <?php foreach ($items as $n): ?>
        <li class="notice-row<?= (int) $n['is_read'] ? '' : ' unread' ?>"><?php partial('notify_item', ['note' => $n]); ?></li>
    <?php endforeach; ?>
</ul>
