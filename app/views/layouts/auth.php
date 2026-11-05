<?php
/** @var string $contentView */
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset_url('images/logo.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="auth-body">
<a class="skip-link" href="#main">Skip to content</a>
<main id="main" class="auth-main">
    <?php require $contentView; ?>
</main>
<script>
(function () {
  var form = document.querySelector('.login-form');
  if (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
    });
  }
  document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
    var wrap = button.closest('.password-wrap');
    var input = wrap ? wrap.querySelector('input') : null;
    var showIcon = button.querySelector('.is-show');
    var hideIcon = button.querySelector('.is-hide');
    if (!input) return;
    button.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-pressed', show ? 'true' : 'false');
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      button.title = show ? 'Hide password' : 'Show password';
      if (showIcon) showIcon.hidden = show;
      if (hideIcon) hideIcon.hidden = !show;
    });
  });
})();
</script>
</body>
</html>
