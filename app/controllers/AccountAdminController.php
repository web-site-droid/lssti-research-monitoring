<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Helpers\Csrf;
use App\Helpers\Role;
use App\Models\LibrarianAccounts;
use App\Models\User;

final class AccountAdminController
{
    public function index(): void
    {
        Auth::requireRole(Role::ACCOUNT_ADMIN);
        view('account_admin/accounts', ['accounts' => LibrarianAccounts::all(), 'success' => flash('success'), 'error' => flash('error')]);
    }

    public function create(): void
    {
        Auth::requireRole(Role::ACCOUNT_ADMIN);
        Csrf::requireValid();
        $data = $this->accountData('admin/accounts');
        $role = User::findRoleBySlug(Role::SCHOOL_ADMINISTRATOR);
        if (!$role) { flash('error', 'The Librarian role is not configured.'); redirect('admin/accounts'); }
        // Ignore any posted role/department: this form can only create final-publisher Librarians.
        $data['role_id'] = (int) $role['id'];
        $data['program_id'] = null;
        $id = User::create($data, (int) Auth::user()['id']);
        Audit::log('user.create', 'user', $id, ['school_id' => $data['school_id'], 'role' => Role::SCHOOL_ADMINISTRATOR]);
        flash('success', 'Librarian account created. Share its temporary password with the account holder.');
        redirect('admin/accounts');
    }

    public function activity(): void
    {
        Auth::requireRole(Role::ACCOUNT_ADMIN);
        $accounts = LibrarianAccounts::all();
        $rawId = trim((string) ($_GET['user_id'] ?? ''));
        $id = $rawId === '' ? null : (ctype_digit($rawId) ? (int) $rawId : 0);
        if ($id !== null && !in_array($id, array_map(static fn (array $a): int => (int) $a['id'], $accounts), true)) {
            http_response_code(403);
            view('errors/forbidden', ['message' => 'Only Librarian account activity is available.']);
            return;
        }
        view('account_admin/activity', ['accounts' => $accounts, 'selectedId' => $id, 'logs' => LibrarianAccounts::activity($id)]);
    }

    public function setup(): void
    {
        Auth::requireRole(Role::SCHOOL_ADMINISTRATOR);
        view('account_admin/setup', ['exists' => LibrarianAccounts::adminExists(), 'success' => flash('success'), 'error' => flash('error')]);
    }

    public function setupSubmit(): void
    {
        Auth::requireRole(Role::SCHOOL_ADMINISTRATOR);
        Csrf::requireValid();
        $data = $this->accountData('admin/setup');
        try {
            $id = LibrarianAccounts::createAdmin($data, (int) Auth::user()['id']);
        } catch (\RuntimeException $error) {
            flash('error', $error->getMessage()); redirect('admin/setup');
        }
        Audit::log('admin.setup', 'user', $id);
        flash('success', 'Admin created. Sign out and sign in with its ID no. and temporary password.');
        redirect('admin/setup');
    }

    private function accountData(string $back): array
    {
        $data = [
            'school_id' => strtoupper(trim((string) ($_POST['school_id'] ?? ''))),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'middle_name' => trim((string) ($_POST['middle_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''), 'is_active' => 1,
        ];
        if ($data['school_id'] === '' || strlen($data['school_id']) > 32 || $data['first_name'] === '' || $data['last_name'] === '' || strlen($data['password']) < 8) {
            flash('error', 'Enter an ID no. (up to 32 characters), first and last names, and a temporary password of at least 8 characters.');
            redirect($back);
        }
        if (User::findBySchoolId($data['school_id'])) {
            flash('error', 'ID no. already has an account.'); redirect($back);
        }
        return $data;
    }
}
