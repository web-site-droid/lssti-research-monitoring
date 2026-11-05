<?php
use App\Helpers\Role;
$role = \App\Helpers\Role::canonical((string) ($user['role_slug'] ?? ''), (string) ($user['role_name'] ?? ''));
?>
<nav class="main-nav<?= $role === Role::STUDENT ? ' nav-boxed' : '' ?>" aria-label="Main">
    <a href="<?= e(url('dashboard')) ?>" data-nav-match="dashboard"><?= icon('clipboard') ?> Home</a>
    <?php if ($role === Role::SCHOOL_ADMINISTRATOR): ?>
        <a href="<?= e(url('study')) ?>" data-nav-match="study"><?= icon('book') ?> Study</a>
        <a href="<?= e(url('review')) ?>" data-nav-match="review"><?= icon('eye') ?> Review</a>
        <a href="<?= e(url('users')) ?>" data-nav-match="users"><?= icon('users') ?> People</a>
        <a href="<?= e(url('reports')) ?>" data-nav-match="reports"><?= icon('list') ?> Logs</a>
        <a href="<?= e(url('settings')) ?>" data-nav-match="settings"><?= icon('chart') ?> Reports</a>
    <?php elseif ($role === Role::SCHOOL_RESEARCH_COORDINATOR): ?>
        <a href="<?= e(url('study')) ?>" data-nav-match="study"><?= icon('book') ?> Study</a>
        <a href="<?= e(url('users')) ?>" data-nav-match="users"><?= icon('users') ?> People</a>
        <a href="<?= e(url('reports')) ?>" data-nav-match="reports"><?= icon('list') ?> Logs</a>
    <?php elseif ($role === Role::DEAN): ?>
        <a href="<?= e(url('review')) ?>" data-nav-match="review"><?= icon('eye') ?> Review</a>
        <a href="<?= e(url('users')) ?>" data-nav-match="users"><?= icon('users') ?> People</a>
        <a href="<?= e(url('reports')) ?>" data-nav-match="reports"><?= icon('list') ?> Logs</a>
    <?php elseif ($role === Role::DEPT_RESEARCH_COORDINATOR): ?>
        <a href="<?= e(url('dashboard')) ?>#drc-dept-table" data-nav-match="library library/review"><?= icon('eye') ?> Review</a>
    <?php elseif ($role === Role::LIBRARIAN): ?>
        <a href="<?= e(url('reports')) ?>" data-nav-match="reports"><?= icon('list') ?> Logs</a>
        <a href="<?= e(url('users')) ?>" data-nav-match="users"><?= icon('users') ?> People</a>
    <?php elseif ($role === Role::RESEARCH_ADVISER): ?>
        <a href="<?= e(url('review')) ?>" data-nav-match="review"><?= icon('eye') ?> Review</a>
    <?php elseif ($role === Role::STUDENT): ?>
        <button type="button" class="nav-guide" data-open-guide><?= icon('help') ?> Guide</button>
        <a href="<?= e(url('studies/create')) ?>" data-nav-match="studies/create studies/edit studies/revise"><?= icon('upload') ?> Upload</a>
        <span class="nav-fuse" role="group" aria-label="Track and waiting">
            <a href="<?= e(url('track')) ?>" data-nav-match="track" data-track-nav="review"><?= icon('flag') ?> Track</a>
            <a href="<?= e(url('track', ['tab' => 'waiting'])) ?>" data-nav-match="track approval" data-track-nav="waiting"><?= icon('clock') ?> Waiting</a>
        </span>
        <span class="nav-account">
            <button type="button" class="icon-btn header-bell" data-open-notify aria-label="Alerts" title="Alerts"><?= icon('bell') ?><?php if (($unreadTotal ?? 0) > 0): ?> <span class="nav-badge"><?= (int) $unreadTotal ?></span><?php endif; ?></button>
            <a class="nav-icon" href="<?= e(url('profile')) ?>" data-nav-match="profile profile/update password/change" aria-label="Profile" title="Profile"><?= icon('user') ?></a>
            <a class="nav-signout" href="<?= e(url('logout')) ?>" aria-label="Sign out" title="Sign out"><?= icon('logout') ?></a>
        </span>
    <?php endif; ?>
    <?php if ($role !== Role::STUDENT): ?>
        <a class="nav-signout" href="<?= e(url('logout')) ?>"><?= icon('logout') ?> Sign out</a>
    <?php endif; ?>
</nav>
<?php if ($role === Role::STUDENT): ?>
    <?php require APP_PATH . '/views/partials/guide_modal.php'; ?>
<?php endif; ?>
