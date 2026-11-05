<div class="page-header"><h1>Librarian accounts</h1><p class="lead">Create Librarian accounts and review their sign-in and system activity.</p></div>
<?php if ($success): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<section class="card">
    <div class="account-admin-heading"><h2>Librarians <span class="badge"><?= count($accounts) ?></span></h2><button type="button" class="btn btn-primary" data-account-dialog-open>Add Librarian</button></div>
    <label class="table-search">Search accounts<input type="search" data-table-filter="#librarian-accounts" placeholder="Name or ID no."></label>
    <div class="table-wrap"><table class="data-table" id="librarian-accounts"><thead><tr><th>ID no.</th><th>Name</th><th>Status</th><th>Activity</th></tr></thead><tbody>
    <?php if (!$accounts): ?><tr><td colspan="4">No Librarian accounts yet.</td></tr><?php endif; ?>
    <?php foreach ($accounts as $account): ?><tr>
        <td><?= e($account['school_id']) ?></td><td><?= e(trim($account['first_name'] . ' ' . $account['last_name'])) ?></td>
        <td><?= (int) $account['is_active'] ? 'Active' : 'Inactive' ?></td>
        <td><a href="<?= e(url('admin/activity', ['user_id' => $account['id']])) ?>">View activity</a></td>
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
<?php $pageScripts = ['account-admin.js']; ?>
