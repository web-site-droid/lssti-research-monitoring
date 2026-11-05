<h1>Access denied</h1>
<p><?= e($message ?? 'You do not have permission to view this resource.') ?></p>
<p><a href="<?= e(url(\App\Helpers\Auth::check() ? 'dashboard' : 'login')) ?>">Return to dashboard</a></p>
