<?php
use App\Helpers\Role;
$user = $user ?? \App\Helpers\Auth::user();
if (!$user) {
    return;
}
$role = Role::canonical((string) ($user['role_slug'] ?? ''), (string) ($user['role_name'] ?? ''));
$who = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
    . ' · '
    . Role::label((string) ($user['role_slug'] ?? ''), (string) ($user['role_name'] ?? ''));
?>
<div class="nav-sheet" id="nav-sheet" hidden>
    <div class="nav-sheet-card" role="dialog" aria-modal="true" aria-labelledby="nav-sheet-who">
        <button type="button" class="nav-sheet-close" data-nav-close aria-label="Close"><?= icon('x') ?></button>
        <p class="nav-sheet-who" id="nav-sheet-who"><?= e($who) ?></p>
        <nav class="nav-sheet-list" aria-label="Menu">
            <a href="<?= e(url('dashboard')) ?>" data-nav-match="dashboard">Home</a>
            <?php if ($role === Role::SCHOOL_ADMINISTRATOR): ?>
                <a href="<?= e(url('study')) ?>" data-nav-match="study">Study</a>
                <a href="<?= e(url('review')) ?>" data-nav-match="review">Review</a>
                <a href="<?= e(url('users')) ?>" data-nav-match="users">People</a>
                <a href="<?= e(url('reports')) ?>" data-nav-match="reports">Logs</a>
                <a href="<?= e(url('settings')) ?>" data-nav-match="settings">Reports</a>
            <?php elseif ($role === Role::SCHOOL_RESEARCH_COORDINATOR): ?>
                <a href="<?= e(url('study')) ?>" data-nav-match="study">Study</a>
                <a href="<?= e(url('users')) ?>" data-nav-match="users">People</a>
                <a href="<?= e(url('reports')) ?>" data-nav-match="reports">Logs</a>
            <?php elseif ($role === Role::DEAN): ?>
                <a href="<?= e(url('review')) ?>" data-nav-match="review">Review</a>
                <a href="<?= e(url('users')) ?>" data-nav-match="users">People</a>
                <a href="<?= e(url('reports')) ?>" data-nav-match="reports">Logs</a>
            <?php elseif ($role === Role::DEPT_RESEARCH_COORDINATOR): ?>
                <a href="<?= e(url('library')) ?>" data-nav-match="library library/review">Review</a>
            <?php elseif ($role === Role::LIBRARIAN): ?>
                <a href="<?= e(url('reports')) ?>" data-nav-match="reports">Logs</a>
                <a href="<?= e(url('users')) ?>" data-nav-match="users">People</a>
            <?php elseif ($role === Role::RESEARCH_ADVISER): ?>
                <a href="<?= e(url('review')) ?>" data-nav-match="review">Review</a>
            <?php elseif ($role === Role::STUDENT): ?>
                <button type="button" class="nav-guide" data-open-guide>Guide</button>
                <a href="<?= e(url('studies/create')) ?>" data-nav-match="studies/create studies/edit studies/revise">Upload</a>
                <a href="<?= e(url('track')) ?>" data-nav-match="track" data-track-nav="review">Track</a>
                <a href="<?= e(url('track', ['tab' => 'waiting'])) ?>" data-nav-match="track approval" data-track-nav="waiting">Waiting</a>
            <?php endif; ?>
            <a href="<?= e(url('profile')) ?>" data-nav-match="profile profile/update password/change">Profile</a>
            <a class="nav-sheet-out" href="<?= e(url('logout')) ?>">Sign out</a>
        </nav>
    </div>
</div>
