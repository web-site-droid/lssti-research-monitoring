<?php $pageTitle = 'Change password'; ?>
<section class="card card-elevated password-card">
    <div class="page-header">
        <h1>Change your password</h1>
        <p class="lead">Use 8 or more letters or numbers. You will use this next time you sign in.</p>
    </div>
    <?php if (!empty($error)): ?><p class="alert alert-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
    <form method="post" action="<?= e(url('password/change/submit')) ?>" class="stack form-friendly">
        <?= \App\Helpers\Csrf::field() ?>
        <div class="form-field">
            <label for="current_password">Current password <span class="req">*</span></label>
            <div class="password-wrap">
                <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
                <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false" title="Show password">
                    <span class="password-icon is-show"><?= icon('eye') ?></span>
                    <span class="password-icon is-hide" hidden><?= icon('eye-off') ?></span>
                </button>
            </div>
        </div>
        <div class="form-field">
            <label for="new_password">New password <span class="req">*</span></label>
            <div class="password-wrap">
                <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
                <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false" title="Show password">
                    <span class="password-icon is-show"><?= icon('eye') ?></span>
                    <span class="password-icon is-hide" hidden><?= icon('eye-off') ?></span>
                </button>
            </div>
        </div>
        <div class="form-field">
            <label for="confirm_password">Type the new password again <span class="req">*</span></label>
            <div class="password-wrap">
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
                <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false" title="Show password">
                    <span class="password-icon is-show"><?= icon('eye') ?></span>
                    <span class="password-icon is-hide" hidden><?= icon('eye-off') ?></span>
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><?= icon('key') ?> Save new password</button>
    </form>
</section>
