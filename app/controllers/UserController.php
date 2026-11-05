<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Role;
use App\Helpers\Spreadsheet;
use App\Helpers\UserImport;
use App\Models\Program;
use App\Models\User;
use RuntimeException;

final class UserController
{
    public function index(): void
    {
        $actor = Auth::user();
        $roleSlug = $actor['role_slug'] ?? '';
        $adviserMode = $roleSlug === Role::RESEARCH_ADVISER;
        $deanMode = $roleSlug === Role::DEAN;
        $coordinatorMode = $roleSlug === Role::DEPT_RESEARCH_COORDINATOR;
        $deptMode = $adviserMode || $deanMode || $coordinatorMode;
        $programId = (int) ($actor['program_id'] ?? 0);
        $adminMode = $roleSlug === Role::SCHOOL_ADMINISTRATOR;
        $canManageDepartments = Role::canManageDepartments((string) $roleSlug, (string) ($actor['role_name'] ?? ''));
        $librarianMode = $roleSlug === Role::LIBRARIAN;
        $allowedSlugs = $this->creatableSlugs($roleSlug);
        $roles = $this->rolesForActor($roleSlug, $allowedSlugs);
        $editRoles = $deptMode ? $roles : User::roles();
        $hideLibrarian = static function (array $role): bool {
            return ($role['slug'] ?? '') !== Role::LIBRARIAN;
        };
        $roles = array_values(array_filter($roles, $hideLibrarian));
        $editRoles = array_values(array_filter($editRoles, $hideLibrarian));
        if ($librarianMode) {
            $hideAdmin = static function (array $role): bool {
                return ($role['slug'] ?? '') !== Role::SCHOOL_ADMINISTRATOR;
            };
            $roles = array_values(array_filter($roles, $hideAdmin));
            $editRoles = array_values(array_filter($editRoles, $hideAdmin));
        }
        if ($adviserMode) {
            $users = $programId > 0 ? User::studentsInProgram($programId) : [];
        } elseif ($coordinatorMode) {
            $users = $programId > 0
                ? User::inProgram($programId, [Role::STUDENT, Role::RESEARCH_ADVISER])
                : [];
        } elseif ($deptMode) {
            $users = $programId > 0
                ? User::inProgram($programId, Role::departmentBoundRoles())
                : [];
        } elseif ($librarianMode) {
            $users = array_values(array_filter(
                User::all(),
                static function (array $user): bool {
                    return ($user['role_slug'] ?? '') !== Role::SCHOOL_ADMINISTRATOR;
                }
            ));
        } else {
            $users = User::all();
        }
        if ($deanMode) {
            $users = array_values(array_filter($users, static fn (array $account): bool =>
                (int) ($account['created_by'] ?? 0) === (int) $actor['id']
            ));
        }
        $users = array_values(array_filter($users, static fn (array $account): bool => ($account['role_slug'] ?? '') !== Role::ACCOUNT_ADMIN));
        $roles = array_values(array_filter($roles, static fn (array $role): bool => ($role['slug'] ?? '') !== Role::ACCOUNT_ADMIN));
        $editRoles = array_values(array_filter($editRoles, static fn (array $role): bool => ($role['slug'] ?? '') !== Role::ACCOUNT_ADMIN));
        view('users/index', [
            'users' => $users,
            'adviserMode' => $adviserMode,
            'coordinatorMode' => $coordinatorMode,
            'deanMode' => $deanMode,
            'adminMode' => $adminMode,
            'deptMode' => $deptMode,
            'departmentLabel' => trim(($actor['program_code'] ?? '') . ' — ' . ($actor['program_name'] ?? ''), ' —'),
            'success' => flash('success'),
            'error' => flash('error'),
            'importNotes' => flash('import_notes'),
            'roles' => $roles,
            'editRoles' => $editRoles,
            'programs' => $deptMode ? [] : ($canManageDepartments ? Program::active() : Program::directorAdded()),
            'canAddDepartment' => $canManageDepartments,
            'canManageDepartments' => $canManageDepartments,
            'canAssignUserDepartment' => $adminMode,
            'studentRoleId' => $adviserMode ? (int) ((User::findRoleBySlug('student')['id'] ?? 0)) : 0,
            'openEdit' => $deanMode ? 0 : (int) ($_GET['edit'] ?? 0),
            'openAdd' => (int) ($_GET['add'] ?? 0) === 1,
        ]);
    }

    public function createForm(): void
    {
        redirect('users', ['add' => 1]);
    }

    public function createSubmit(): void
    {
        Csrf::requireValid();
        $data = [
            'school_id' => trim($_POST['school_id'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'middle_name' => trim($_POST['middle_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'role_id' => (int) ($_POST['role_id'] ?? 0),
            'password' => trim($_POST['password'] ?? '') !== '' ? $_POST['password'] : UserImport::DEFAULT_PASSWORD,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($data['school_id'] === '' || $data['first_name'] === '' || $data['last_name'] === '' || !$data['role_id']) {
            flash('error', 'Fill in all required fields.');
            redirect('users', ['add' => 1]);
        }
        if (!User::roleExists($data['role_id'])) {
            flash('error', 'Choose a role.');
            redirect('users', ['add' => 1]);
        }
        $this->protectAdminAccount(0, $data['role_id']);
        if (strlen($data['password']) < 8) {
            flash('error', 'Password must be at least 8 characters.');
            redirect('users', ['add' => 1]);
        }
        if (User::findBySchoolId($data['school_id'])) {
            flash('error', 'ID no. already exists.');
            redirect('users', ['add' => 1]);
        }
        $actor = Auth::user();
        $roleSlug = $actor['role_slug'] ?? '';
        if (in_array($roleSlug, [Role::RESEARCH_ADVISER, Role::DEAN, Role::DEPT_RESEARCH_COORDINATOR], true)) {
            $role = User::findRole($data['role_id']);
            $programId = (int) ($actor['program_id'] ?? 0);
            $allowed = $this->creatableSlugs($roleSlug);
            if (
                !$role
                || !in_array($role['slug'] ?? '', $allowed, true)
                || $programId <= 0
                || !Program::find($programId)
            ) {
                flash('error', $roleSlug === Role::DEAN
                    ? 'Choose student, research adviser, or dept research coordinator. Accounts stay in your department.'
                    : 'Choose student or research adviser. Accounts stay in your department.');
                redirect('users', ['add' => 1]);
            }
            $data['program_id'] = $programId;
        } else {
            $role = User::findRole($data['role_id']);
            if ($roleSlug === Role::SCHOOL_ADMINISTRATOR) {
                if (!$role || !in_array($role['slug'], Role::schoolAdminCreatableRoles(), true)) {
                    flash('error', 'Choose dean or school research coordinator.');
                    redirect('users', ['add' => 1]);
                }
            }
            if ($roleSlug === Role::LIBRARIAN && (!$role || ($role['slug'] ?? '') === Role::SCHOOL_ADMINISTRATOR)) {
                flash('error', 'You cannot add a librarian account.');
                redirect('users', ['add' => 1]);
            }
            $department = $this->departmentFromPost($data['role_id']);
            if ($department['error'] !== null) {
                flash('error', $department['error']);
                redirect('users', ['add' => 1]);
            }
            $data['program_id'] = $department['program_id'];
        }
        $id = User::create($data, (int) Auth::user()['id']);
        Audit::log('user.create', 'user', $id, ['school_id' => $data['school_id']]);
        flash('success', in_array($roleSlug, [Role::RESEARCH_ADVISER, Role::DEAN, Role::DEPT_RESEARCH_COORDINATOR], true)
            ? 'Account added to your department.'
            : 'User created.');
        redirect('users');
    }

    public function editForm(): void
    {
        if ((Auth::user()['role_slug'] ?? '') === Role::DEAN) {
            flash('error', 'Dean account access is view-only.');
            redirect('users');
        }
        $id = (int) ($_GET['id'] ?? 0);
        redirect('users', $id > 0 ? ['edit' => $id] : []);
    }

    public function editSubmit(): void
    {
        Csrf::requireValid();
        if ((Auth::user()['role_slug'] ?? '') === Role::DEAN) {
            flash('error', 'Deans cannot edit accounts.');
            redirect('users');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $this->protectAdminAccount($id, (int) ($_POST['role_id'] ?? 0));
        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'middle_name' => trim($_POST['middle_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'role_id' => (int) ($_POST['role_id'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        $actor = Auth::user();
        $roleSlug = $actor['role_slug'] ?? '';
        if (in_array($roleSlug, [Role::RESEARCH_ADVISER, Role::DEAN, Role::DEPT_RESEARCH_COORDINATOR], true)) {
            $target = User::findById($id);
            $programId = (int) ($actor['program_id'] ?? 0);
            $role = User::findRole($data['role_id']);
            $allowed = $this->creatableSlugs($roleSlug);
            if (
                !$target
                || !in_array($target['role_slug'] ?? '', $allowed, true)
                || (int) ($target['program_id'] ?? 0) !== $programId
            ) {
                flash('error', $roleSlug === Role::DEAN
                    ? 'You can edit student, adviser, and dept research coordinator accounts in your department only.'
                    : 'You can edit student and research adviser accounts in your department only.');
                redirect('users');
            }
            if ($data['first_name'] === '' || $data['last_name'] === '' || !$role || !in_array($role['slug'], $allowed, true)) {
                flash('error', $roleSlug === Role::DEAN
                    ? 'Choose student, research adviser, or dept research coordinator and fill in the name.'
                    : 'Choose student or research adviser and fill in the name.');
                redirect('users', ['edit' => $id]);
            }
            $data['program_id'] = (int) ($target['created_by'] ?? 0) === (int) ($actor['id'] ?? 0)
                ? $programId
                : ($target['program_id'] ?? null);
            User::update($id, $data);
            Audit::log('user.update', 'user', $id);
            flash('success', 'Account updated.');
            redirect('users');
        }
        if ($data['first_name'] === '' || $data['last_name'] === '' || !User::roleExists($data['role_id'])) {
            flash('error', 'Choose a role and fill in the name.');
            redirect('users', ['edit' => $id]);
        }
        $target = User::findById($id);
        if (!$target) {
            flash('error', 'Account not found.');
            redirect('users');
        }
        if ($roleSlug === Role::LIBRARIAN) {
            $newRole = User::findRole($data['role_id']);
            if (($target['role_slug'] ?? '') === Role::SCHOOL_ADMINISTRATOR || ($newRole['slug'] ?? '') === Role::SCHOOL_ADMINISTRATOR) {
                flash('error', 'You cannot change a librarian account.');
                redirect('users');
            }
        }
        if ($roleSlug === Role::SCHOOL_ADMINISTRATOR) {
            $newRole = User::findRole($data['role_id']);
            $sameRole = ($newRole['slug'] ?? '') === ($target['role_slug'] ?? '');
            if (!$newRole || (!in_array($newRole['slug'], Role::schoolAdminCreatableRoles(), true) && !$sameRole)) {
                flash('error', 'Choose dean or school research coordinator.');
                redirect('users', ['edit' => $id]);
            }
        }
        if (in_array($roleSlug, [Role::SCHOOL_ADMINISTRATOR, Role::SCHOOL_RESEARCH_COORDINATOR], true)) {
            $identity = strtoupper(trim((string) ($_POST['school_id'] ?? $target['school_id'])));
            $existing = $identity !== '' ? User::findBySchoolId($identity) : null;
            if ($identity === '' || strlen($identity) > 32 || ($existing && (int) $existing['id'] !== $id)) {
                flash('error', 'Enter a unique ID no. of up to 32 characters.');
                redirect('users', ['edit' => $id]);
            }
            $data['school_id'] = $identity;
        }
        $canChangeDepartment = $roleSlug === Role::SCHOOL_ADMINISTRATOR
            || Role::canManageDepartments($roleSlug, (string) ($actor['role_name'] ?? ''))
            || (int) ($target['created_by'] ?? 0) === (int) ($actor['id'] ?? 0);
        if ($canChangeDepartment) {
            $department = $this->departmentFromPost($data['role_id']);
            if ($department['error'] !== null) {
                flash('error', $department['error']);
                redirect('users', ['edit' => $id]);
            }
            $data['program_id'] = $department['program_id'];
        } else {
            $data['program_id'] = $target['program_id'] ?? null;
        }
        try {
            User::update($id, $data);
        } catch (\PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) !== 1062) throw $error;
            flash('error', 'That ID no. is already in use. Choose another one.');
            redirect('users', ['edit' => $id]);
        }
        Audit::log('user.update', 'user', $id, isset($data['school_id']) ? ['old_id_no' => $target['school_id'], 'new_id_no' => $data['school_id']] : null);
        flash('success', 'User updated.');
        redirect('users');
    }

    public function deleteSubmit(): void
    {
        Auth::requireRole([Role::SCHOOL_ADMINISTRATOR, Role::SCHOOL_RESEARCH_COORDINATOR]);
        Csrf::requireValid();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $account = User::deleteWithoutAcademicRecords($id, (int) Auth::user()['id']);
        } catch (\PDOException $error) {
            flash('error', 'This account could not be deleted because it is linked to existing records. Deactivate it using Edit instead.');
            redirect('users');
        } catch (\RuntimeException $error) {
            flash('error', $error->getMessage());
            redirect('users');
        }
        Audit::log('user.delete', 'user', $id, ['school_id' => $account['school_id'], 'role' => $account['role_slug']]);
        flash('success', 'Account deleted.');
        redirect('users');
    }

    public function resetPasswordSubmit(): void
    {
        $this->denyDeanAccountChanges();
        Csrf::requireValid();
        $id = (int) ($_POST['id'] ?? 0);
        $this->protectAdminAccount($id);
        $actor = Auth::user();
        $roleSlug = $actor['role_slug'] ?? '';
        if (in_array($roleSlug, [Role::RESEARCH_ADVISER, Role::DEAN, Role::DEPT_RESEARCH_COORDINATOR], true)) {
            $target = User::findById($id);
            $programId = (int) ($actor['program_id'] ?? 0);
            $allowed = $this->creatableSlugs($roleSlug);
            if (
                !$target
                || !in_array($target['role_slug'] ?? '', $allowed, true)
                || (int) ($target['program_id'] ?? 0) !== $programId
                || $programId <= 0
                || ($roleSlug === Role::DEAN && (int) ($target['created_by'] ?? 0) !== (int) $actor['id'])
            ) {
                flash('error', $roleSlug === Role::DEAN
                    ? 'You can reset passwords only for accounts you added in your department.'
                    : 'You can reset passwords for student and research adviser accounts in your department only.');
                redirect('users');
            }
        }
        if (in_array($roleSlug, [Role::LIBRARIAN, Role::SCHOOL_ADMINISTRATOR], true)) {
            $target = User::findById($id);
            if (!$target) {
                flash('error', 'Account not found.');
                redirect('users');
            }
            if (($target['role_slug'] ?? '') === Role::SCHOOL_ADMINISTRATOR || ($target['role_slug'] ?? '') === Role::LIBRARIAN) {
                // allowed: librarian may reset all accounts, including other librarian accounts
            }
        }
        $password = trim($_POST['password'] ?? '') !== '' ? $_POST['password'] : UserImport::DEFAULT_PASSWORD;
        if (strlen($password) < 8) {
            flash('error', 'Password must be at least 8 characters.');
            redirect('users', ['edit' => $id]);
        }
        User::resetPassword($id, $password);
        Audit::log('user.reset_password', 'user', $id);
        flash('success', 'Password reset.');
        redirect('users');
    }

    public function importTemplate(): void
    {
        $binary = Spreadsheet::xlsx(UserImport::templateSheets());
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="LSSTI-account-format.xlsx"');
        header('Content-Length: ' . strlen($binary));
        header('Cache-Control: no-store');
        echo $binary;
        exit;
    }

    public function importSubmit(): void
    {
        Csrf::requireValid();
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Choose an Excel file to import.');
            redirect('users');
        }
        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            flash('error', 'The Excel file must be 2 MB or smaller.');
            redirect('users');
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            flash('error', 'Use the Excel format (.xlsx). You can also upload a CSV saved from that file.');
            redirect('users');
        }
        try {
            $rows = Spreadsheet::readRows((string) $file['tmp_name'], $ext);
            $actor = Auth::user();
            $roleSlug = $actor['role_slug'] ?? '';
            $limit = [];
            if ($roleSlug === Role::RESEARCH_ADVISER) {
                $programId = (int) ($actor['program_id'] ?? 0);
                if ($programId <= 0) {
                    flash('error', 'Your account has no department, so accounts cannot be imported.');
                    redirect('users');
                }
                $limit = ['program_id' => $programId, 'roles' => ['student', 'research_adviser']];
            } elseif ($roleSlug === Role::DEAN) {
                $programId = (int) ($actor['program_id'] ?? 0);
                if ($programId <= 0) {
                    flash('error', 'Your account has no department, so accounts cannot be imported.');
                    redirect('users');
                }
                $limit = ['program_id' => $programId, 'roles' => Role::deanCreatableRoles()];
            } elseif ($roleSlug === Role::DEPT_RESEARCH_COORDINATOR) {
                $programId = (int) ($actor['program_id'] ?? 0);
                if ($programId <= 0) {
                    flash('error', 'Your account has no department, so accounts cannot be imported.');
                    redirect('users');
                }
                $limit = ['program_id' => $programId, 'roles' => [Role::STUDENT, Role::RESEARCH_ADVISER]];
            } elseif ($roleSlug === Role::LIBRARIAN) {
                $limit = [
                    'roles' => array_values(array_filter(
                        Role::all(),
                        static function (string $slug): bool {
                            return $slug !== Role::SCHOOL_ADMINISTRATOR;
                        }
                    )),
                ];
            } elseif ($roleSlug === Role::SCHOOL_ADMINISTRATOR) {
                $limit = [
                    'create_departments' => true,
                    'roles' => Role::schoolAdminCreatableRoles(),
                ];
            } elseif ($roleSlug === Role::SCHOOL_RESEARCH_COORDINATOR) {
                $limit = [
                    'create_departments' => true,
                ];
            }
            $result = UserImport::import($rows, (int) $actor['id'], $limit);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('users');
        }
        if ($result['created'] > 0) {
            Audit::log('user.import', 'user', null, ['created' => $result['created']]);
            flash('success', $result['created'] === 1 ? '1 account imported.' : $result['created'] . ' accounts imported.');
        } else {
            flash('error', 'No accounts were added.');
        }
        if ($result['notes'] !== []) {
            flash('import_notes', implode("\n", $result['notes']));
        }
        redirect('users');
    }

    public function departmentSave(): void
    {
        Csrf::requireValid();
        $actor = Auth::user();
        if (!Role::canManageDepartments((string) ($actor['role_slug'] ?? ''), (string) ($actor['role_name'] ?? ''))) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $code = (string) ($_POST['code'] ?? '');
        $name = (string) ($_POST['name'] ?? '');
        try {
            if ($id > 0) {
                Program::update($id, $code, $name);
                Audit::log('program.update', 'program', $id);
                flash('success', 'Department updated.');
            } else {
                $newId = Program::create($code, $name, true);
                Audit::log('program.create', 'program', $newId);
                flash('success', 'Department added.');
            }
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('users');
    }

    public function loginLogs(): void
    {
        redirect('reports');
    }

    /** @return array{program_id: ?int, error: ?string} */
    private function departmentFromPost(int $roleId): array
    {
        $role = User::findRole($roleId);
        $actor = Auth::user();
        $actorSlug = (string) ($actor['role_slug'] ?? '');
        $bound = $role && in_array($role['slug'], Role::departmentBoundRoles(), true);
        $canManage = Role::canManageDepartments($actorSlug, (string) ($actor['role_name'] ?? ''));
        $canAssignAny = $actorSlug === Role::SCHOOL_ADMINISTRATOR;
        $raw = (string) ($_POST['program_id'] ?? '');
        if (!$bound && !$canAssignAny) {
            return ['program_id' => null, 'error' => null];
        }
        if ($raw === 'new') {
            if (!$canManage) {
                return ['program_id' => null, 'error' => 'Only the librarian or school research coordinator can add a department.'];
            }
            try {
                $id = Program::create((string) ($_POST['new_program_code'] ?? ''), (string) ($_POST['new_program_name'] ?? ''), true);
            } catch (\InvalidArgumentException $e) {
                return ['program_id' => null, 'error' => $e->getMessage()];
            }
            return ['program_id' => $id, 'error' => null];
        }
        if ($raw === '' || $raw === '0') {
            if ($bound) {
                return ['program_id' => null, 'error' => 'Choose a department.'];
            }
            return ['program_id' => null, 'error' => null];
        }
        $program = ($canManage || $canAssignAny)
            ? Program::find((int) $raw)
            : Program::findDirectorAdded((int) $raw);
        if (!$program) {
            return ['program_id' => null, 'error' => ($canManage || $canAssignAny)
                ? 'Choose a department.'
                : 'Choose a department the librarian added.'];
        }
        return ['program_id' => (int) $program['id'], 'error' => null];
    }

    /** @return list<string> */
    private function protectAdminAccount(int $id, int $roleId = 0): void
    {
        $target = $id > 0 ? User::findById($id) : null;
        $role = $roleId > 0 ? User::findRole($roleId) : null;
        if (($target['role_slug'] ?? '') === Role::ACCOUNT_ADMIN || ($role['slug'] ?? '') === Role::ACCOUNT_ADMIN) {
            flash('error', 'Admin accounts cannot be changed through People management.');
            redirect('users');
        }
    }

    private function denyDeanAccountChanges(): void
    {
        if ((Auth::user()['role_slug'] ?? '') === Role::DEAN) {
            flash('error', 'Dean account access is view-only.');
            redirect('users');
        }
    }

    private function creatableSlugs(string $actorSlug): array
    {
        return match ($actorSlug) {
            Role::RESEARCH_ADVISER, Role::DEPT_RESEARCH_COORDINATOR => [Role::STUDENT, Role::RESEARCH_ADVISER],
            Role::DEAN => Role::deanCreatableRoles(),
            Role::SCHOOL_ADMINISTRATOR => Role::schoolAdminCreatableRoles(),
            default => Role::all(),
        };
    }

    /**
     * @param list<string> $allowedSlugs
     * @return list<array<string,mixed>>
     */
    private function rolesForActor(string $actorSlug, array $allowedSlugs): array
    {
        if (!in_array($actorSlug, [Role::RESEARCH_ADVISER, Role::DEAN, Role::DEPT_RESEARCH_COORDINATOR, Role::SCHOOL_ADMINISTRATOR], true)) {
            return User::roles();
        }
        $roles = [];
        foreach (User::roles() as $role) {
            if (in_array($role['slug'], $allowedSlugs, true)) {
                $roles[] = $role;
            }
        }
        return $roles;
    }
}
