<div class="page-header">
    <h1>Edit account</h1>
    <p class="lead"><?= e($editUser['last_name'] . ', ' . $editUser['first_name']) ?></p>
</div>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<section class="card card-elevated">
    <form method="post" action="<?= e(url('users/edit/submit')) ?>" class="stack">
        <?= \App\Helpers\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>">
        <label for="school_id">School ID</label>
        <input id="school_id" value="<?= e($editUser['school_id']) ?>" readonly>
        <label for="first_name">First name <span class="req">*</span></label>
        <input id="first_name" name="first_name" value="<?= e($editUser['first_name']) ?>" required>
        <label for="middle_name">Middle name</label>
        <input id="middle_name" name="middle_name" value="<?= e($editUser['middle_name'] ?? '') ?>">
        <label for="last_name">Last name <span class="req">*</span></label>
        <input id="last_name" name="last_name" value="<?= e($editUser['last_name']) ?>" required>
        <label for="role_id">Role <span class="req">*</span></label>
        <select id="role_id" name="role_id" required>
            <?php foreach ($roles as $r): ?>
                <option value="<?= (int) $r['id'] ?>" data-slug="<?= e($r['slug']) ?>" <?= (int) $r['id'] === (int) $editUser['role_id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php
        $actor = \App\Helpers\Auth::user();
        $canAssignUserDepartment = (($actor['role_slug'] ?? '') === \App\Helpers\Role::SCHOOL_ADMINISTRATOR);
        $canChangeDepartment = $canAssignUserDepartment
            || \App\Helpers\Role::canManageDepartments((string) ($actor['role_slug'] ?? ''), (string) ($actor['role_name'] ?? ''))
            || (int) ($editUser['created_by'] ?? 0) === (int) ($actor['id'] ?? 0);
        $boundRole = in_array($editUser['role_slug'], ['student', 'research_adviser', 'adviser', 'dept_research_coordinator', 'dean'], true);
        ?>
        <div id="department-field" <?= ($canAssignUserDepartment || $boundRole) ? '' : 'hidden' ?>>
            <label for="program_id">Department<?php if ($boundRole): ?> <span class="req">*</span><?php endif; ?></label>
            <?php if (!$canChangeDepartment): ?>
                <p class="muted"><?= e(trim((string) (($editUser['program_code'] ?? '') . ' — ' . ($editUser['program_name'] ?? '')), ' —') ?: 'No department') ?></p>
            <?php endif; ?>
            <select id="program_id" name="program_id"<?= $canChangeDepartment ? '' : ' disabled hidden' ?>>
                <option value=""><?= $canAssignUserDepartment ? 'All departments' : 'Select department' ?></option>
                <?php foreach ($programs as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) ($editUser['program_id'] ?? 0) ? 'selected' : '' ?>><?= e($p['code'] . ' — ' . $p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="check" for="is_active"><input type="checkbox" id="is_active" name="is_active" <?= (int) $editUser['is_active'] ? 'checked' : '' ?>> Active</label>
        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= icon('save') ?> Save</button>
            <a class="btn btn-secondary" href="<?= e(url('users')) ?>"><?= icon('arrow-left') ?> Back to accounts</a>
        </div>
    </form>
</section>
<section class="card card-elevated">
    <h2 class="section-title">Reset password</h2>
    <form method="post" action="<?= e(url('users/reset-password')) ?>" class="stack">
        <?= \App\Helpers\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>">
        <label for="password">New temporary password</label>
        <input id="password" name="password" value="<?= e(\App\Helpers\UserImport::DEFAULT_PASSWORD) ?>" minlength="8" autocomplete="off">
        <button type="submit" class="btn btn-secondary"><?= icon('key') ?> Reset password</button>
    </form>
</section>
