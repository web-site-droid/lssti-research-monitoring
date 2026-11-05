<div class="page-header"><h1>Admin account setup</h1><p class="lead">Create the first Admin for Librarian account creation and activity logs.</p></div>
<?php if ($success): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<?php if ($exists): ?>
<section class="card"><h2>Admin is already configured</h2><p>The one-time setup is closed. The Admin signs in using their own ID no. and password.</p></section>
<?php else: ?>
<section class="card"><form method="post" action="<?= e(url('admin/setup/submit')) ?>" class="stack">
<?= \App\Helpers\Csrf::field() ?>
<p>This is a separate account. The Admin can create Librarians and view their recorded activity, but cannot review or publish studies.</p>
<?php require __DIR__ . '/account_form.php'; ?>
<button class="btn btn-primary" type="submit">Create first Admin</button>
</form></section>
<?php endif; ?>
