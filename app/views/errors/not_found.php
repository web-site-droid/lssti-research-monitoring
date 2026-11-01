<h1>Page not found</h1>
<p>The requested page does not exist.</p>
<p><a href="<?= e(url(\App\Helpers\Auth::check() ? 'dashboard' : 'login')) ?>">Return to dashboard</a></p>
