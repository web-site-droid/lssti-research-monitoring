<?php
/** @var string $contentView */
$user = \App\Helpers\Auth::user();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset_url('images/lssti-seal.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('vendor/sweetalert2/sweetalert2.min.css')) ?>">
</head>
<?php $useSidebar = $user && \App\Helpers\Role::canonical((string) ($user['role_slug'] ?? '')) !== \App\Helpers\Role::STUDENT; ?>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<?php if ($useSidebar): ?><div class="app-shell"><?php partial('sidebar', ['user' => $user]); ?><div class="app-main"><?php partial('topbar', ['user' => $user]); ?><?php else: ?><?php partial('header', ['user' => $user]); ?><?php endif; ?>
<?php if ($user): ?><?php partial('nav_sheet', ['user' => $user]); ?><?php endif; ?>
        <main id="main" class="container<?= !empty($contentWide) ? ' container-wide' : '' ?>">
            <?php require $contentView; ?>
        </main>
<?php if ($useSidebar): ?></div></div><?php endif; ?>
<script src="<?= e(asset_url('vendor/sweetalert2/sweetalert2.min.js')) ?>"></script>
<script src="<?= e(asset_url('js/app.js')) ?>"></script>
<?php if (!empty($pageScripts)): ?>
    <?php foreach ($pageScripts as $script): ?>
        <script src="<?= e(asset_url('js/' . $script)) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
