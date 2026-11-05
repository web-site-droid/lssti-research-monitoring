<div class="form-grid">
    <div><label for="account-id"><?= !empty($adminIdentity) ? 'Username' : 'ID no.' ?></label><input id="account-id" name="<?= !empty($adminIdentity) ? 'username' : 'school_id' ?>" maxlength="32" required autocomplete="off"></div>
    <div><label for="account-first">First name</label><input id="account-first" name="first_name" required></div>
    <div><label for="account-middle">Middle name <span class="muted">(optional)</span></label><input id="account-middle" name="middle_name"></div>
    <div><label for="account-last">Last name</label><input id="account-last" name="last_name" required></div>
    <div><label for="account-password">Temporary password</label><input id="account-password" name="password" type="password" minlength="8" required autocomplete="new-password"><p class="muted">At least 8 characters. Share it with the account holder; they must change it after signing in.</p></div>
</div>
