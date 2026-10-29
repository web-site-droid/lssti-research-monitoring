<?php
/** @var string $contentView */
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset_url('images/logo.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('vendor/sweetalert2/sweetalert2.min.css')) ?>">
</head>
<body class="auth-body">
<a class="skip-link" href="#main">Skip to content</a>
<main id="main" class="auth-main">
    <?php require $contentView; ?>
</main>
<script src="<?= e(asset_url('vendor/sweetalert2/sweetalert2.min.js')) ?>"></script>
<script src="<?= e(asset_url('js/app.js')) ?>"></script>
</body>
</html>
