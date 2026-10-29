<?php $user = $user ?? \App\Helpers\Auth::user(); ?>
<header class="site-header">
    <button type="button" class="menu-toggle icon-btn" data-nav-toggle="header" aria-expanded="false"><?= icon('list') ?> <span class="icon-label">Menu</span></button>
    <a class="brand brand-link" href="<?= e(url('')) ?>">
        <img src="<?= e(asset_url('images/logo.png')) ?>" alt="Department of Computer Science, LSSTI" width="48" height="48">
        <div>
            <strong>LSSTI Research Repository</strong>
            <?php if ($user): ?>
                <span class="user-meta"><?= e($user['first_name'] . ' ' . $user['last_name']) ?> · <?= e(\App\Helpers\Role::label((string) ($user['role_slug'] ?? ''), (string) ($user['role_name'] ?? ''))) ?></span>
            <?php else: ?>
                <span class="user-meta">Search approved studies</span>
            <?php endif; ?>
        </div>
    </a>
    <?php if ($user): ?>
        <?php
        $notes = \App\Models\Notification::forUser((int) $user['id'], 200);
        $unreadTotal = \App\Models\Notification::unreadCount((int) $user['id']);
        ?>
        <?php partial('nav', ['user' => $user, 'unreadTotal' => $unreadTotal]); ?>
        <?php require APP_PATH . '/views/partials/notify_modal.php'; ?>
    <?php else: ?>
        <?php partial('nav_guest'); ?>
    <?php endif; ?>
</header>
