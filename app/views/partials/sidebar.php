<?php $user = $user ?? \App\Helpers\Auth::user(); ?>
<aside class="site-sidebar" id="site-sidebar">
    <a class="brand brand-link" href="<?= e(url($user ? 'dashboard' : 'login')) ?>">
        <img src="<?= e(asset_url('images/lssti-seal.png')) ?>" alt="LSSTI Research Repository" width="48" height="48">
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
        <?php partial('nav', ['user' => $user]); ?>
    <?php else: ?>
        <?php partial('nav_guest'); ?>
    <?php endif; ?>
</aside>
