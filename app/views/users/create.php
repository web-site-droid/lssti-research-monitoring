<?php
$adviserMode = !empty($adviserMode);
$deanMode = !empty($deanMode);
$adminMode = !empty($adminMode);
$deptMode = !empty($deptMode) || $adviserMode || $deanMode;
?>
<div class="page-header">
    <h1><?= $deptMode ? 'Add account' : 'Add person' ?></h1>
    <p class="lead"><?php
        if ($deanMode) {
            echo 'Choose student, research adviser, or dept research coordinator. The account is added to your department' . ($departmentLabel !== '' ? ', ' . e($departmentLabel) : '') . '.';
        } elseif ($adviserMode) {
            echo 'Choose student or adviser. The account is added to your department' . ($departmentLabel !== '' ? ', ' . e($departmentLabel) : '') . '. They sign in with the school ID and temporary password.';
        } elseif ($adminMode) {
            echo 'Choose dean or school research coordinator. A dean needs a department.';
        } else {
            echo !empty($canAddDepartment)
                ? 'Create an account. You can choose an existing department or add a new one.'
                : 'Create an account. For department-bound roles, choose a department the school administrator added.';
        }
    ?></p>
</div>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<?php if ($deptMode && $departmentLabel === ''): ?>
    <p class="alert alert-error">Your account has no department, so an account cannot be added.</p>
<?php else: ?>
    <section class="card card-elevated">
    <form method="post" action="<?= e(url('users/create/submit')) ?>" class="stack">
        <?= \App\Helpers\Csrf::field() ?>
        <label for="school_id">School ID <span class="req">*</span></label>
        <input id="school_id" name="school_id" required autocomplete="off">
        <label for="first_name">First name <span class="req">*</span></label>
        <input id="first_name" name="first_name" required>
        <label for="middle_name">Middle name</label>
        <input id="middle_name" name="middle_name">
        <label for="last_name">Last name <span class="req">*</span></label>
        <input id="last_name" name="last_name" required>
        <label for="role_id">Role <span class="req">*</span></label>
        <select id="role_id" name="role_id" required<?= $deptMode ? '' : ' data-department-role="department-field"' ?>>
            <?php foreach ($roles as $r): ?>
                <option value="<?= (int) $r['id'] ?>" data-slug="<?= e($r['slug']) ?>"><?= e($r['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($deptMode): ?>
            <p><strong>Department</strong><br><?= e($departmentLabel) ?></p>
        <?php else: ?>
        <div id="department-field" hidden>
            <label for="program_id">Department <span class="req">*</span></label>
            <select id="program_id" name="program_id">
                <option value="">Select department</option>
                <?php foreach ($programs as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= e($p['code'] . ' — ' . $p['name']) ?></option>
                <?php endforeach; ?>
                <?php if (!empty($canAddDepartment)): ?>
                    <option value="new">Add a department</option>
                <?php endif; ?>
            </select>
            <?php if (!empty($canAddDepartment)): ?>
            <div id="new-department" data-new-department="program_id" hidden>
                <label for="new_program_code">Department code</label>
                <input id="new_program_code" name="new_program_code" maxlength="16" placeholder="BSBA" autocomplete="off">
                <label for="new_program_name">Department name</label>
                <input id="new_program_name" name="new_program_name" maxlength="128" placeholder="Bachelor of Science in Business Administration">
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <label for="password">Temporary password</label>
        <input id="password" name="password" value="<?= e(\App\Helpers\UserImport::DEFAULT_PASSWORD) ?>" minlength="8" autocomplete="off">
        <label class="check" for="is_active"><input type="checkbox" id="is_active" name="is_active" checked> Active</label>
        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= icon('plus') ?> <?= $deptMode ? 'Add account' : 'Add person' ?></button>
            <a class="btn btn-secondary" href="<?= e(url('users')) ?>"><?= icon('arrow-left') ?> Back to accounts</a>
        </div>
    </form>
</section>
<?php endif; ?>
