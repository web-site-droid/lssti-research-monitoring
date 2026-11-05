<?php $adminLogin = !empty($adminLogin); $pageTitle = ($adminLogin ? 'Admin sign in' : 'Sign in') . ' · ' . APP_NAME; ?>
<div class="login-shell">
    <aside class="login-hero" aria-label="About this system">
        <img class="login-logo" src="<?= e(asset_url('images/lssti-seal.png')) ?>" alt="LSSTI Research Repository" width="80" height="80">
        <p class="login-school">LSSTI</p>
        <h1 class="login-title">Digital Research Repository</h1>
        <p class="login-tagline"><?= $adminLogin ? 'Admin access for Librarian accounts and activity logs. Sign in with your username and password.' : 'For students, faculty, and staff on the school network. Sign in with your official ID no.' ?></p>
    </aside>

    <section class="login-panel" aria-labelledby="login-heading">
        <div class="login-panel-inner">
            <nav class="login-account-switch" aria-label="Choose sign-in account type">
                <a href="<?= e(url('login')) ?>" <?= !$adminLogin ? 'aria-current="page"' : '' ?>><?= icon('users') ?><span>Students &amp; Staff</span></a>
                <a href="<?= e(url('admin/login')) ?>" <?= $adminLogin ? 'aria-current="page"' : '' ?>><?= icon('key') ?><span>Admin</span></a>
            </nav>
            <h2 id="login-heading" class="login-panel-title"><?= $adminLogin ? 'Admin sign in' : 'Sign in to your account' ?></h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error login-alert" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url($adminLogin ? 'admin/login/submit' : 'login/submit')) ?>" class="login-form">
                <?= \App\Helpers\Csrf::field() ?>
                <?php if (!empty($next)): ?>
                    <input type="hidden" name="next" value="<?= e($next) ?>">
                <?php endif; ?>
                <div class="form-field">
                    <label for="login-identity"><?= $adminLogin ? 'Username' : 'ID no.' ?> <span class="req">*</span></label>
                    <input
                        type="text"
                        id="login-identity"
                        name="<?= $adminLogin ? 'username' : 'school_id' ?>"
                        class="form-control"
                        required
                        autocomplete="username"
                        placeholder="<?= $adminLogin ? 'Enter your username' : 'e.g. STU-0001' ?>"
                        autofocus
                    >
                </div>
                <div class="form-field">
                    <label for="password">Password <span class="req">*</span></label>
                    <div class="password-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                        >
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false" title="Show password">
                            <span class="password-icon is-show"><?= icon('eye') ?></span>
                            <span class="password-icon is-hide" hidden><?= icon('eye-off') ?></span>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary login-submit"><?= icon('login') ?> Sign in</button>
                <p class="login-help">Forgot your password or locked out? Visit the Research Office on campus.</p>
            </form>
        </div>
    </section>
</div>
