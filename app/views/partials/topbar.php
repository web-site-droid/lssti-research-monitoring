<?php
$user = $user ?? \App\Helpers\Auth::user();
$notes = $user ? \App\Models\Notification::forUser((int) $user['id'], 200) : [];
$unreadTotal = $user ? \App\Models\Notification::unreadCount((int) $user['id']) : 0;
?>
<header class="topbar">
    <button type="button" class="menu-toggle icon-btn" data-nav-toggle="sidebar" aria-expanded="false" aria-controls="site-sidebar"><?= icon('list') ?> <span class="icon-label">Menu</span></button>
    <nav class="topbar-actions" aria-label="Account">
        <button type="button" class="icon-btn" data-open-notify aria-label="Alerts"><?= icon('bell') ?><?php if ($unreadTotal > 0): ?> <span class="nav-badge"><?= (int) $unreadTotal ?></span><?php endif; ?></button>
        <?php
        $route = (string) ($_GET['route'] ?? '');
        $profileOn = $route === 'profile' || str_starts_with($route, 'profile/') || $route === 'password/change';
        ?>
        <a class="icon-btn<?= $profileOn ? ' is-current' : '' ?>" href="<?= e(url('profile')) ?>" aria-label="Profile"<?= $profileOn ? ' aria-current="page"' : '' ?>><?= icon('user') ?></a>
    </nav>
</header>
<?php require APP_PATH . '/views/partials/notify_modal.php'; ?>
