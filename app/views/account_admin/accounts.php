<div class="page-header"><h1>Librarian accounts</h1><p class="lead">Create and manage Librarian accounts and review their recorded activity.</p></div>
<?php if ($success): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<section class="card">
    <div class="account-admin-heading"><h2>Librarians <span class="badge"><?= count($accounts) ?></span></h2><button type="button" class="btn btn-primary" data-account-dialog-open>Add Librarian</button></div>
    <label class="table-search">Search accounts<input type="search" data-table-filter="#librarian-accounts" placeholder="Name or ID no."></label>
    <div class="table-wrap"><table class="data-table" id="librarian-accounts"><thead><tr><th>ID no.</th><th>Name</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php if (!$accounts): ?><tr><td colspan="4">No Librarian accounts yet.</td></tr><?php endif; ?>
    <?php foreach ($accounts as $account): ?><tr>
        <td><?= e($account['school_id']) ?></td><td><?= e(trim($account['first_name'] . ' ' . $account['last_name'])) ?></td>
        <td><?= (int) $account['is_active'] ? 'Active' : 'Inactive' ?></td>
        <td class="actions-cell"><div class="actions">
            <a href="<?= e(url('admin/activity', ['user_id' => $account['id']])) ?>">Activity</a>
            <button type="button" class="btn btn-secondary btn-sm" data-librarian-edit="<?= (int) $account['id'] ?>"
                data-identity="<?= e($account['school_id']) ?>" data-first="<?= e($account['first_name']) ?>"
                data-middle="<?= e($account['middle_name'] ?? '') ?>" data-last="<?= e($account['last_name']) ?>"
                data-active="<?= (int) $account['is_active'] ?>">Edit</button>
            <form method="post" action="<?= e(url('admin/accounts/delete')) ?>" data-confirm="<?= e('Permanently delete Librarian ' . $account['school_id'] . '? Accounts linked to academic records must be deactivated instead.') ?>">
                <?= \App\Helpers\Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"><?= icon('trash') ?> Delete</button>
            </form>
        </div></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<dialog id="account-admin-create" class="preview-modal" aria-labelledby="account-create-title">
    <form method="post" action="<?= e(url('admin/accounts/create')) ?>" class="stack">
        <?= \App\Helpers\Csrf::field() ?>
        <div class="preview-modal-head"><h2 id="account-create-title">Add Librarian</h2><button type="button" class="btn btn-secondary" data-account-dialog-close>Close</button></div>
        <p class="muted">This account receives the existing Librarian research and publication permissions.</p>
        <?php require __DIR__ . '/account_form.php'; ?>
        <div class="actions"><button type="button" class="btn btn-secondary" data-account-dialog-close>Cancel</button><button type="submit" class="btn btn-primary">Create Librarian</button></div>
    </form>
</dialog>
<dialog id="account-admin-edit" class="preview-modal" aria-labelledby="librarian-edit-title">
    <form method="post" action="<?= e(url('admin/accounts/edit')) ?>" class="stack">
        <?= \App\Helpers\Csrf::field() ?><input type="hidden" name="id" id="librarian-edit-id">
        <div class="preview-modal-head"><h2 id="librarian-edit-title">Edit Librarian</h2><button type="button" class="btn btn-secondary" data-librarian-edit-close>Close</button></div>
        <label for="librarian-edit-identity">ID no.</label><input id="librarian-edit-identity" name="school_id" maxlength="32" required>
        <p class="muted">This is the ID no. the Librarian uses to sign in.</p>
        <label for="librarian-edit-first">First name</label><input id="librarian-edit-first" name="first_name" required>
        <label for="librarian-edit-middle">Middle name</label><input id="librarian-edit-middle" name="middle_name">
        <label for="librarian-edit-last">Last name</label><input id="librarian-edit-last" name="last_name" required>
        <label class="check"><input id="librarian-edit-active" name="is_active" type="checkbox"> Active account</label>
        <p class="muted">Deactivate accounts with academic records. At least one Librarian must remain active.</p>
        <div class="actions"><button type="button" class="btn btn-secondary" data-librarian-edit-close>Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
    </form>
</dialog>
<?php $pageScripts = ['account-admin.js']; ?>
