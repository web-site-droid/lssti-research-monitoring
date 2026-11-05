<?php
$adviserMode = !empty($adviserMode);
$coordinatorMode = !empty($coordinatorMode);
$deanMode = !empty($deanMode);
$adminMode = !empty($adminMode);
$deptMode = !empty($deptMode) || $adviserMode || $deanMode || $coordinatorMode;
$editRoles = $editRoles ?? $roles ?? [];
?>
<div class="page-header">
    <h1>People</h1>
    <p class="lead"><?php
        if ($adminMode) {
            echo 'Add a dean or school research coordinator. Edit any account and change that person’s department. You can also open every full manuscript school-wide.';
        } elseif (!empty($canManageDepartments)) {
            echo 'Add people and assign a department.';
        } elseif ($deanMode) {
            echo 'Add accounts in your department and view their details. Existing accounts are read-only.';
        } elseif ($coordinatorMode) {
            echo 'Research advisers and students in your department' . ($departmentLabel !== '' ? ' (' . e($departmentLabel) . ')' : '') . '.';
        } elseif ($adviserMode) {
            echo 'Add a student or teacher in your department' . ($departmentLabel !== '' ? ' (' . e($departmentLabel) . ')' : '') . '.';
        } else {
            echo 'Add people and find who can sign in.';
        }
    ?></p>
</div>
<?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<section class="card card-elevated import-card">
    <h2 class="section-title">Import from Excel</h2>
    <p class="lead"><?php
        if ($adminMode) {
            echo 'Get the file, fill one person each row, then upload. Use dean or school research coordinator only.';
        } elseif ($deanMode) {
            echo 'Get the file, fill one person each row, then upload. Use student, research adviser, or dept research coordinator only.';
        } elseif ($coordinatorMode) {
            echo 'Get the file, fill one person each row, then upload. Use student or research adviser only. Accounts stay in your department.';
        } elseif ($adviserMode) {
            echo 'Get the file, fill one person each row, then upload. Use student or teacher only.';
        } else {
            echo 'Get the file, fill one person each row, then upload.';
        }
    ?></p>
    <div class="import-actions">
        <form class="import-form" method="post" action="<?= e(url('users/import')) ?>" enctype="multipart/form-data">
            <?= \App\Helpers\Csrf::field() ?>
            <label class="file-field">Excel file <span class="req">*</span>
                <input type="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            </label>
            <a class="btn btn-secondary" href="<?= e(url('users/import/template')) ?>"><?= icon('download') ?> Download format</a>
            <button type="submit" class="btn btn-primary"><?= icon('upload') ?> Import accounts</button>
        </form>
    </div>
    <?php if (!empty($importNotes)): ?>
        <ul class="import-notes">
            <?php foreach (preg_split("/\r\n|\n|\r/", $importNotes) ?: [] as $note): ?>
                <?php if (trim($note) !== ''): ?><li><?= e($note) ?></li><?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<p class="toolbar">
    <button type="button" class="btn btn-primary" data-open-add <?= $deptMode && $departmentLabel === '' ? 'disabled' : '' ?>><?= icon('user-plus') ?> <?= $deptMode ? 'Add account' : 'Add person' ?></button>
</p>
<?php
$departments = [];
foreach ($users as $person) {
    $code = trim((string) ($person['program_code'] ?? ''));
    if ($code !== '') {
        $departments[$code] = $code;
    }
}
foreach ($programs ?? [] as $program) {
    $code = trim((string) ($program['code'] ?? ''));
    if ($code !== '') {
        $departments[$code] = $code;
    }
}
ksort($departments);
?>
<div class="people-filters">
    <label class="table-search">Search accounts
        <input type="search" data-table-filter="#people-table" placeholder="<?= ($adviserMode || $coordinatorMode) ? 'Name, ID no., or role' : 'Name, ID no., role, or department' ?>">
    </label>
    <?php if (!$adviserMode && !$coordinatorMode): ?>
    <label class="table-search is-filter" for="people-dept-filter">Department
        <select id="people-dept-filter" data-table-dept="#people-table">
            <option value="">All departments</option>
            <?php foreach ($departments as $code): ?>
                <option value="<?= e($code) ?>"><?= e($code) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php endif; ?>
</div>
<p id="people-table-empty" class="empty-state" hidden>No accounts match.</p>
<div class="table-wrap">
<table class="data-table<?= $deanMode ? ' dean-people-table' : '' ?>" data-sortable id="people-table">
    <thead><tr><th>ID no.</th><th>Name</th><th>Role</th><th>Department</th><th>Active</th><th data-nosort>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <?php $programCode = trim((string) ($u['program_code'] ?? '')); ?>
        <tr data-program="<?= e($programCode) ?>"<?= $programCode === '' ? ' data-all-depts="1"' : '' ?>>
            <td><?= e($u['school_id']) ?></td>
            <td><?= e($u['last_name'] . ', ' . $u['first_name']) ?></td>
            <td><span class="role-pill"><?= e($u['role_name']) ?></span></td>
            <td><?= $programCode !== '' ? e($programCode) : 'All departments' ?></td>
            <td><span class="status-pill <?= (int) $u['is_active'] ? 'is-on' : 'is-off' ?>"><?= (int) $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td class="actions-cell">
                <div class="actions">
                <?php if ($deanMode): ?>
                <button type="button" class="btn btn-secondary btn-sm" data-dean-view
                    data-account-name="<?= e(trim($u['first_name'] . ' ' . ($u['middle_name'] ?? '') . ' ' . $u['last_name'])) ?>"
                    data-account-school-id="<?= e($u['school_id']) ?>"
                    data-account-role="<?= e($u['role_name']) ?>"
                    data-account-department="<?= e(trim(($u['program_code'] ?? '') . ' - ' . ($u['program_name'] ?? ''), ' -')) ?>"
                    data-account-active="<?= (int) $u['is_active'] ? 'Active' : 'Inactive' ?>"><?= icon('eye') ?> View account</button>
                <?php else: ?>
                <button type="button" class="btn btn-secondary btn-sm" data-edit-account
                    data-id="<?= (int) $u['id'] ?>"
                    data-school-id="<?= e($u['school_id']) ?>"
                    data-first="<?= e($u['first_name']) ?>"
                    data-middle="<?= e($u['middle_name'] ?? '') ?>"
                    data-last="<?= e($u['last_name']) ?>"
                    data-role="<?= (int) $u['role_id'] ?>"
                    data-program="<?= (int) ($u['program_id'] ?? 0) ?>"
                    data-program-label="<?= e(trim((string) (($u['program_code'] ?? '') . ' — ' . ($u['program_name'] ?? '')), ' —')) ?>"
                    data-created="<?= (int) ($u['created_by'] ?? 0) ?>"
                    data-active="<?= (int) $u['is_active'] ?>"><?= icon('pencil') ?> Edit</button>
                <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($deanMode): ?>
<?php $pageScripts = array_merge($pageScripts ?? [], ['dean-people.js']); ?>
<dialog id="dean-account-dialog" class="preview-modal dean-account-dialog" aria-labelledby="dean-account-title">
        <div class="preview-modal-head">
            <h2 id="dean-account-title">Account details</h2>
            <button type="button" class="btn btn-secondary btn-icon-only" data-dean-view-close aria-label="Close"><?= icon('x') ?></button>
        </div>
        <p class="muted">Read-only account information</p>
        <dl class="dean-account-details">
            <?php foreach (['name' => 'Full name', 'school-id' => 'ID no.', 'role' => 'Role', 'department' => 'Department', 'active' => 'Account status'] as $key => $label): ?>
            <div><dt><?= e($label) ?></dt><dd data-dean-detail="<?= e($key) ?>"></dd></div>
            <?php endforeach; ?>
        </dl>
        <div class="actions"><button type="button" class="btn btn-secondary" data-dean-view-close>Close</button></div>
</dialog>
<?php endif; ?>
<?php if (!$deanMode): ?>
<dialog id="edit-account" class="preview-modal profile-modal" data-open-edit="<?= (int) ($openEdit ?? 0) ?>" data-actor-id="<?= (int) ((\App\Helpers\Auth::user()['id'] ?? 0)) ?>" data-manage-dept="<?= !empty($canManageDepartments) ? '1' : '0' ?>" data-assign-dept="<?= !empty($canAssignUserDepartment) ? '1' : '0' ?>">
    <form method="post" action="<?= e(url('users/edit/submit')) ?>" class="profile-editor stack">
        <?= \App\Helpers\Csrf::field() ?>
        <div class="preview-modal-head">
            <h2>Edit account</h2>
            <button type="button" class="btn btn-secondary btn-icon-only" data-close-edit aria-label="Close"><?= icon('x') ?></button>
        </div>
        <input type="hidden" name="id" id="edit-id" value="">
        <?php if ($deptMode): ?>
            <input type="hidden" name="program_id" value="<?= (int) (\App\Helpers\Auth::user()['program_id'] ?? 0) ?>">
        <?php endif; ?>
        <div class="profile-landscape">
            <div>
                <label for="edit-school-id">ID no.</label>
                <input id="edit-school-id" readonly>
            </div>
            <div>
                <label for="edit-first-name">First name <span class="req">*</span></label>
                <input id="edit-first-name" name="first_name" required>
            </div>
            <div>
                <label for="edit-middle-name">Middle name</label>
                <input id="edit-middle-name" name="middle_name">
            </div>
            <div>
                <label for="edit-last-name">Last name <span class="req">*</span></label>
                <input id="edit-last-name" name="last_name" required>
            </div>
            <div>
                <label for="role_id">Role <span class="req">*</span></label>
                <select id="role_id" name="role_id" required<?= $deptMode ? '' : ' data-department-role="department-field"' ?>>
                    <?php foreach ($editRoles as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" data-slug="<?= e($r['slug']) ?>"><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <?php if ($deptMode): ?>
                    <label for="edit-department">Department</label>
                    <input id="edit-department" value="<?= e($departmentLabel) ?>" readonly>
                <?php else: ?>
                    <div id="department-field"<?= !empty($canAssignUserDepartment) ? '' : ' hidden' ?>>
                        <label for="program_id">Department <span id="department-req" class="req"<?= !empty($canAssignUserDepartment) ? ' hidden' : '' ?>>*</span></label>
                        <input id="program-locked" readonly hidden>
                        <select id="program_id" name="program_id">
                            <option value=""><?= !empty($canAssignUserDepartment) ? 'All departments' : 'Select department' ?></option>
                            <?php foreach ($programs ?? [] as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= e($p['code'] . ' — ' . $p['name']) ?></option>
                            <?php endforeach; ?>
                            <?php if (!empty($canAddDepartment)): ?>
                                <option value="new">Add a department</option>
                            <?php endif; ?>
                        </select>
                        <?php if (!empty($canAddDepartment)): ?>
                        <div data-new-department="program_id" hidden>
                            <label for="edit_program_code">Department code</label>
                            <input id="edit_program_code" name="new_program_code" maxlength="16" placeholder="BSBA" autocomplete="off">
                            <label for="edit_program_name">Department name</label>
                            <input id="edit_program_name" name="new_program_name" maxlength="128" placeholder="Bachelor of Science in Business Administration">
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <label class="check" for="edit-active"><input type="checkbox" id="edit-active" name="is_active"> Active</label>
            </div>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= icon('save') ?> Save</button>
            <button type="button" class="btn btn-secondary" data-close-edit>Cancel</button>
        </div>
    </form>
    <h2 class="section-title">Reset password</h2>
    <form method="post" action="<?= e(url('users/reset-password')) ?>" class="stack">
        <?= \App\Helpers\Csrf::field() ?>
        <input type="hidden" name="id" id="edit-reset-id" value="">
        <div class="profile-landscape">
            <div>
                <label for="edit-password">New temporary password</label>
                <input id="edit-password" name="password" value="<?= e(\App\Helpers\UserImport::DEFAULT_PASSWORD) ?>" minlength="8" autocomplete="off">
            </div>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-secondary"><?= icon('key') ?> Reset password</button>
        </div>
    </form>
</dialog>
<?php endif; ?>
<dialog id="add-account" class="preview-modal profile-modal" data-open-add="<?= !empty($openAdd) ? '1' : '0' ?>">
    <form method="post" action="<?= e(url('users/create/submit')) ?>" class="profile-editor stack">
        <?= \App\Helpers\Csrf::field() ?>
        <div class="preview-modal-head">
            <h2><?= $deptMode ? 'Add account' : 'Add person' ?></h2>
            <button type="button" class="btn btn-secondary btn-icon-only" data-close-add aria-label="Close"><?= icon('x') ?></button>
        </div>
        <?php if ($deptMode && $departmentLabel === ''): ?>
            <p class="alert alert-error">Your account has no department, so an account cannot be added.</p>
        <?php else: ?>
        <div class="profile-landscape">
            <div>
                <label for="add-school-id">ID no. <span class="req">*</span></label>
                <input id="add-school-id" name="school_id" required autocomplete="off">
            </div>
            <div>
                <label for="add-first-name">First name <span class="req">*</span></label>
                <input id="add-first-name" name="first_name" required>
            </div>
            <div>
                <label for="add-middle-name">Middle name</label>
                <input id="add-middle-name" name="middle_name">
            </div>
            <div>
                <label for="add-last-name">Last name <span class="req">*</span></label>
                <input id="add-last-name" name="last_name" required>
            </div>
            <div>
                <label for="add-role-id">Role <span class="req">*</span></label>
                <select id="add-role-id" name="role_id" required<?= $deptMode ? '' : ' data-department-role="add-department-field"' ?>>
                    <?php foreach ($roles ?? [] as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" data-slug="<?= e($r['slug']) ?>"><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <?php if ($deptMode): ?>
                    <label for="add-department">Department</label>
                    <input id="add-department" value="<?= e($departmentLabel) ?>" readonly>
                <?php else: ?>
                    <div id="add-department-field" hidden>
                        <label for="add-program-id">Department <span class="req">*</span></label>
                        <select id="add-program-id" name="program_id">
                            <option value="">Select department</option>
                            <?php foreach ($programs ?? [] as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= e($p['code'] . ' — ' . $p['name']) ?></option>
                            <?php endforeach; ?>
                            <?php if (!empty($canAddDepartment)): ?>
                                <option value="new">Add a department</option>
                            <?php endif; ?>
                        </select>
                        <?php if (!empty($canAddDepartment)): ?>
                        <div data-new-department="add-program-id" hidden>
                            <label for="add_program_code">Department code</label>
                            <input id="add_program_code" name="new_program_code" maxlength="16" placeholder="BSBA" autocomplete="off">
                            <label for="add_program_name">Department name</label>
                            <input id="add_program_name" name="new_program_name" maxlength="128" placeholder="Bachelor of Science in Business Administration">
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <label for="add-password">Temporary password</label>
                <input id="add-password" name="password" value="<?= e(\App\Helpers\UserImport::DEFAULT_PASSWORD) ?>" minlength="8" autocomplete="off">
            </div>
            <div>
                <label class="check" for="add-active"><input type="checkbox" id="add-active" name="is_active" checked> Active</label>
            </div>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= icon('plus') ?> <?= $deptMode ? 'Add account' : 'Add person' ?></button>
            <button type="button" class="btn btn-secondary" data-close-add>Cancel</button>
        </div>
        <?php endif; ?>
    </form>
</dialog>
