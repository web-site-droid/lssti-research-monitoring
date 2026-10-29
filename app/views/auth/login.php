<?php $pageTitle = 'Sign in · ' . APP_NAME; ?>
<div class="login-shell">
    <aside class="login-hero" aria-label="About this system">
        <img class="login-logo" src="<?= e(asset_url('images/logo.png')) ?>" alt="Department of Computer Science, LSSTI" width="80" height="80">
        <p class="login-school">LSSTI</p>
        <h1 class="login-title">Digital Research Repository</h1>
        <p class="login-tagline">For students, faculty, and staff on the school network. Sign in with your official school ID.</p>
    </aside>

    <section class="login-panel" aria-labelledby="login-heading">
        <div class="login-panel-inner">
            <h2 id="login-heading" class="login-panel-title">Sign in to your account</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error login-alert" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('login/submit')) ?>" class="login-form">
                <?= \App\Helpers\Csrf::field() ?>
                <?php if (!empty($next)): ?>
                    <input type="hidden" name="next" value="<?= e($next) ?>">
                <?php endif; ?>
                <div class="form-field">
                    <label for="school_id">School ID <span class="req">*</span></label>
                    <input
                        type="text"
                        id="school_id"
                        name="school_id"
                        class="form-control"
                        required
                        autocomplete="username"
                        placeholder="e.g. STU-0001"
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
