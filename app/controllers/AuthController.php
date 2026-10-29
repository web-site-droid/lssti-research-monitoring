<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\RateLimit;
use App\Models\LoginLog;
use App\Models\User;

final class AuthController
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            redirect($this->safeNext((string) ($_GET['next'] ?? '')) ?? '');
        }
        view('auth/login', [
            'error' => flash('error'),
            'next' => $this->safeNext((string) ($_GET['next'] ?? '')) ?? '',
        ], 'auth');
    }

    public function loginSubmit(): void
    {
        if (!is_post()) {
            redirect('login');
        }
        Csrf::requireValid();
        $ip = client_ip();
        if (RateLimit::isLoginLocked($ip)) {
            LoginLog::record(null, $_POST['school_id'] ?? null, 'login_failed');
            flash('error', 'Too many failed attempts. Try again later.');
            redirect('login');
        }

        $schoolId = trim($_POST['school_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = User::findBySchoolId($schoolId);

        if (!$user || !(int) $user['is_active'] || !User::passwordMatches((string) $user['password_hash'], $password)) {
            LoginLog::record($user['id'] ?? null, $schoolId, 'login_failed');
            flash('error', 'Invalid school ID or password.');
            redirect('login');
        }
        if (!str_starts_with((string) $user['password_hash'], '$2y$') && !str_starts_with((string) $user['password_hash'], '$argon2')) {
            User::updatePassword((int) $user['id'], $password, false);
        }

        Auth::login($user);
        LoginLog::record((int) $user['id'], $schoolId, 'login_success');
        $next = $this->safeNext((string) ($_POST['next'] ?? ''));
        if ((int) $user['must_change_password'] === 1) {
            if ($next) {
                $_SESSION['after_password'] = $next;
            }
            flash('error', 'You must change your password before continuing.');
            redirect('password/change');
        }
        redirect($this->destinationAfterLogin($user, $next));
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user) {
            LoginLog::record((int) $user['id'], $user['school_id'], 'logout');
        }
        Auth::logout();
        redirect('login');
    }

    public function profile(): void
    {
        $user = Auth::user();
        $studies = in_array($user['role_slug'], ['student', 'research_adviser'], true)
            ? \App\Models\Study::listSubmittedBy((int) $user['id'])
            : [];
        view('auth/profile', [
            'user' => $user,
            'studies' => $studies,
            'programs' => \App\Models\Program::active(),
            'departmentRequired' => in_array($user['role_slug'] ?? '', \App\Helpers\Role::departmentBoundRoles(), true),
            'bookmarks' => \App\Models\Study::bookmarksFor((int) $user['id']),
            'downloads' => \App\Models\Study::downloadHistory((int) $user['id']),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function profileUpdate(): void
    {
        Csrf::requireValid();
        $user = Auth::user();
        if (!$user) {
            redirect('login');
        }
        $roleSlug = $user['role_slug'] ?? '';
        $departmentRequired = in_array($roleSlug, \App\Helpers\Role::departmentBoundRoles(), true);
        $canChangeDepartment = (int) ($user['created_by'] ?? 0) > 0
            && (int) ($user['created_by'] ?? 0) === (int) ($user['id'] ?? 0);
        $programId = $canChangeDepartment
            ? (int) ($_POST['program_id'] ?? 0)
            : (int) ($user['program_id'] ?? 0);
        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'middle_name' => trim($_POST['middle_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'program_id' => $programId > 0 ? $programId : null,
        ];
        $password = trim((string) ($_POST['password'] ?? ''));
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $confirmPassword = trim((string) ($_POST['password_confirm'] ?? ''));
        if ($data['first_name'] === '' || $data['last_name'] === '' || $data['contact_number'] === '' || $data['email'] === '' || $data['address'] === '' || $data['city'] === '' || $data['state'] === '') {
            flash('error', 'Enter your full name, contact number, email, province, city, and barangay.');
            redirect('profile');
        }
        if ($departmentRequired && ($programId <= 0 || !\App\Models\Program::find($programId))) {
            flash('error', 'Choose your department.');
            redirect('profile');
        }
        if (!$departmentRequired && $programId > 0 && !\App\Models\Program::find($programId)) {
            flash('error', 'Choose a valid department, or leave it blank.');
            redirect('profile');
        }
        if (!preg_match('/^[0-9]{11}$/', $data['contact_number'])) {
            flash('error', 'Enter an 11-digit contact number.');
            redirect('profile');
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address.');
            redirect('profile');
        }
        if ($password !== '') {
            if (strlen($password) < 8) {
                flash('error', 'New password must be at least 8 characters.');
                redirect('profile');
            }
            if ($password !== $confirmPassword) {
                flash('error', 'New password and confirmation do not match.');
                redirect('profile');
            }
            if (!User::passwordMatches((string) $user['password_hash'], $currentPassword)) {
                flash('error', 'Enter your current password to set a new one.');
                redirect('profile');
            }
        }
        User::updateProfile((int) $user['id'], $data);
        if ($password !== '') {
            User::updatePassword((int) $user['id'], $password, false);
            Audit::log('user.password_change', 'user', (int) $user['id']);
        }
        if (!empty($_FILES['photo']['name'])) {
            $saved = $this->saveProfilePhoto((int) $user['id'], $_FILES['photo']);
            if ($saved !== true) {
                flash('error', $saved);
                redirect('profile');
            }
        }
        Audit::log('user.profile', 'user', (int) $user['id']);
        flash('success', $password !== '' ? 'Profile and password saved.' : 'Profile saved.');
        redirect('profile');
    }

    public function photo(): void
    {
        $user = Auth::user();
        $relative = (string) ($user['photo_path'] ?? '');
        if ($relative === '' || !\App\Helpers\StorageFiles::exists($relative)) {
            http_response_code(404);
            exit;
        }
        header('Cache-Control: private, max-age=3600');
        \App\Helpers\StorageFiles::stream(
            $relative,
            \App\Helpers\StorageFiles::mime($relative, 'image/jpeg'),
            basename($relative),
            true
        );
    }

    /** @param array<string, mixed> $file @return true|string */
    private function saveProfilePhoto(int $userId, array $file): bool|string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'The picture could not be uploaded.';
        }
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            return 'The picture must be 2 MB or smaller.';
        }
        $info = @getimagesize((string) $file['tmp_name']);
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($types[$info[2]])) {
            return 'Upload a JPG, PNG, or WebP picture.';
        }
        foreach (['jpg', 'png', 'webp'] as $ext) {
            \App\Helpers\StorageFiles::delete('avatars/' . $userId . '.' . $ext);
        }
        $relative = 'avatars/' . $userId . '.' . $types[$info[2]];
        try {
            \App\Helpers\StorageFiles::putUpload($relative, (string) $file['tmp_name'], (string) ($info['mime'] ?? 'image/jpeg'));
        } catch (\Throwable) {
            return 'The picture could not be saved.';
        }
        User::updatePhoto($userId, $relative);
        return true;
    }

    public function changePasswordForm(): void
    {
        view('auth/change_password', ['error' => flash('error'), 'success' => flash('success')]);
    }

    public function changePasswordSubmit(): void
    {
        Csrf::requireValid();
        $user = Auth::user();
        if (!$user) {
            redirect('login');
        }
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!User::passwordMatches((string) $user['password_hash'], $current)) {
            flash('error', 'Current password is incorrect.');
            redirect('password/change');
        }
        if (strlen($new) < 8 || $new !== $confirm) {
            flash('error', 'New password must be at least 8 characters and match confirmation.');
            redirect('password/change');
        }
        User::updatePassword((int) $user['id'], $new, false);
        flash('success', 'Password updated.');
        $next = $_SESSION['after_password'] ?? null;
        unset($_SESSION['after_password']);
        $user = Auth::user() ?? $user;
        $safe = is_string($next) ? $this->safeNext($next) : null;
        redirect($this->destinationAfterLogin($user, $safe));
    }

    private function safeNext(string $next): ?string
    {
        $allowed = ['studies/create', 'studies', 'dashboard', 'search', 'profile'];
        return in_array($next, $allowed, true) ? $next : null;
    }

    /** @param array<string, mixed> $user */
    private function destinationAfterLogin(array $user, ?string $next): string
    {
        if ($next === 'studies/create' && ($user['role_slug'] ?? '') !== 'student') {
            flash('error', 'Only students can upload a study.');
            return 'dashboard';
        }
        if ($next !== null && $next !== '') {
            return $next;
        }
        // Students land on their department catalog dashboard.
        if (($user['role_slug'] ?? '') === 'student') {
            return 'dashboard';
        }
        return 'dashboard';
    }
}
