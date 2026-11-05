<?php

declare(strict_types=1);

namespace App;

use App\Controllers\AuthController;
use App\Controllers\AccountAdminController;
use App\Controllers\DashboardController;
use App\Controllers\FileController;
use App\Controllers\LibraryController;
use App\Controllers\NotificationController;
use App\Controllers\ReportController;
use App\Controllers\ReviewController;
use App\Controllers\SearchController;
use App\Controllers\SettingsController;
use App\Controllers\StudyController;
use App\Controllers\UserController;
use App\Helpers\Auth;
use App\Helpers\Role;

final class Router
{
    /** @var array<string, array{0: class-string, 1: string, auth?: bool, roles?: list<string>}> */
    private array $routes = [];

    public function __construct()
    {
        $people = Role::peopleManagers();
        $dept = Role::deptReviewers();
        $dean = Role::deanReviewers();
        $advisers = Role::advisers();
        $academic = Role::academicApprovers();
        $librarians = Role::librarians();
        $reporters = Role::reporters();
        $admin = Role::finalApprovers();
        $reviewers = array_values(array_unique(array_merge($advisers, $dean, $academic, $admin, [Role::SCHOOL_ADMINISTRATOR])));
        $libraryRoles = array_values(array_unique(array_merge($dept, $librarians, [Role::SCHOOL_ADMINISTRATOR])));
        $reporters = array_values(array_unique(array_merge($reporters, [Role::SCHOOL_ADMINISTRATOR])));

        $this->routes = [
            'admin/login' => [AuthController::class, 'adminLoginForm', 'auth' => false],
            'admin/login/submit' => [AuthController::class, 'adminLoginSubmit', 'auth' => false],
            'admin/accounts' => [AccountAdminController::class, 'index', 'auth' => true, 'roles' => [Role::ACCOUNT_ADMIN]],
            'admin/accounts/create' => [AccountAdminController::class, 'create', 'auth' => true, 'roles' => [Role::ACCOUNT_ADMIN]],
            'admin/activity' => [AccountAdminController::class, 'activity', 'auth' => true, 'roles' => [Role::ACCOUNT_ADMIN]],
            'admin/setup' => [AccountAdminController::class, 'setup', 'auth' => true, 'roles' => [Role::SCHOOL_ADMINISTRATOR]],
            'admin/setup/submit' => [AccountAdminController::class, 'setupSubmit', 'auth' => true, 'roles' => [Role::SCHOOL_ADMINISTRATOR]],
            '' => [SearchController::class, 'landing', 'auth' => false],
            'dashboard' => [DashboardController::class, 'index', 'auth' => true],
            'login' => [AuthController::class, 'loginForm', 'auth' => false],
            'login/submit' => [AuthController::class, 'loginSubmit', 'auth' => false],
            'logout' => [AuthController::class, 'logout', 'auth' => true],
            'profile' => [AuthController::class, 'profile', 'auth' => true],
            'track' => [StudyController::class, 'track', 'auth' => true, 'roles' => [Role::STUDENT]],
            'approval' => [StudyController::class, 'approval', 'auth' => true, 'roles' => [Role::STUDENT]],
            'profile/update' => [AuthController::class, 'profileUpdate', 'auth' => true],
            'profile/photo' => [AuthController::class, 'photo', 'auth' => true],
            'password/change' => [AuthController::class, 'changePasswordForm', 'auth' => true],
            'password/change/submit' => [AuthController::class, 'changePasswordSubmit', 'auth' => true],

            'users' => [UserController::class, 'index', 'auth' => true, 'roles' => $people],
            'users/create' => [UserController::class, 'createForm', 'auth' => true, 'roles' => $people],
            'users/create/submit' => [UserController::class, 'createSubmit', 'auth' => true, 'roles' => $people],
            'users/edit' => [UserController::class, 'editForm', 'auth' => true, 'roles' => $people],
            'users/edit/submit' => [UserController::class, 'editSubmit', 'auth' => true, 'roles' => $people],
            'users/reset-password' => [UserController::class, 'resetPasswordSubmit', 'auth' => true, 'roles' => $people],
            'users/login-logs' => [UserController::class, 'loginLogs', 'auth' => true, 'roles' => $reporters],
            'users/import/template' => [UserController::class, 'importTemplate', 'auth' => true, 'roles' => $people],
            'users/import' => [UserController::class, 'importSubmit', 'auth' => true, 'roles' => $people],
            'users/departments/save' => [UserController::class, 'departmentSave', 'auth' => true, 'roles' => [Role::SCHOOL_ADMINISTRATOR, Role::SCHOOL_RESEARCH_COORDINATOR]],

            'studies' => [StudyController::class, 'index', 'auth' => true],
            'study' => [StudyController::class, 'folders', 'auth' => true, 'roles' => Role::allManuscriptViewers()],
            'studies/create' => [StudyController::class, 'createForm', 'auth' => true, 'roles' => [Role::STUDENT]],
            'studies/create/submit' => [StudyController::class, 'createSubmit', 'auth' => true, 'roles' => [Role::STUDENT]],
            'studies/upload-part' => [StudyController::class, 'uploadPart', 'auth' => true, 'roles' => [Role::STUDENT, Role::SCHOOL_ADMINISTRATOR]],
            'studies/replace-file' => [StudyController::class, 'replaceFile', 'auth' => true, 'roles' => [Role::STUDENT, Role::SCHOOL_ADMINISTRATOR]],
            'studies/edit' => [StudyController::class, 'editForm', 'auth' => true, 'roles' => [Role::STUDENT]],
            'studies/edit/submit' => [StudyController::class, 'editSubmit', 'auth' => true, 'roles' => [Role::STUDENT]],
            'studies/delete' => [StudyController::class, 'deleteSubmit', 'auth' => true, 'roles' => [Role::STUDENT]],
            'studies/view' => [StudyController::class, 'view', 'auth' => true],
            'studies/revise' => [StudyController::class, 'reviseForm', 'auth' => true, 'roles' => [Role::STUDENT]],
            'studies/revise/submit' => [StudyController::class, 'reviseSubmit', 'auth' => true, 'roles' => [Role::STUDENT]],
            'studies/submit' => [StudyController::class, 'submitStudy', 'auth' => true, 'roles' => [Role::STUDENT]],
            'studies/submit-librarian' => [StudyController::class, 'submitToLibrarian', 'auth' => true, 'roles' => [Role::STUDENT]],

            'library' => [LibraryController::class, 'index', 'auth' => true, 'roles' => $libraryRoles],
            'library/review' => [LibraryController::class, 'review', 'auth' => true, 'roles' => $libraryRoles],
            'library/metadata' => [LibraryController::class, 'metadataSubmit', 'auth' => true, 'roles' => $libraryRoles],
            'library/comment' => [LibraryController::class, 'commentSubmit', 'auth' => true, 'roles' => $libraryRoles],
            'library/approve' => [LibraryController::class, 'approveSubmit', 'auth' => true, 'roles' => $libraryRoles],
            'library/accept' => [LibraryController::class, 'acceptSubmit', 'auth' => true, 'roles' => $librarians],
            'library/retract' => [LibraryController::class, 'retractSubmit', 'auth' => true, 'roles' => $librarians],

            'review' => [ReviewController::class, 'index', 'auth' => true, 'roles' => $reviewers],
            'review/ethics' => [ReviewController::class, 'ethicsForm', 'auth' => true, 'roles' => $reviewers],
            'review/ethics/submit' => [ReviewController::class, 'ethicsSubmit', 'auth' => true, 'roles' => $academic],
            'review/comment/submit' => [ReviewController::class, 'commentSubmit', 'auth' => true, 'roles' => $reviewers],
            'review/score/submit' => [ReviewController::class, 'scoreSubmit', 'auth' => true, 'roles' => $reviewers],
            'review/finish' => [ReviewController::class, 'finishSubmit', 'auth' => true, 'roles' => [Role::RESEARCH_ADVISER]],
            'review/dean-approve' => [ReviewController::class, 'deanApproveSubmit', 'auth' => true, 'roles' => $dean],
            'review/plagiarism/submit' => [ReviewController::class, 'plagiarismSubmit', 'auth' => true, 'roles' => $academic],
            'review/approve' => [ReviewController::class, 'approveSubmit', 'auth' => true, 'roles' => $academic],
            'review/final-publish' => [ReviewController::class, 'finalPublishSubmit', 'auth' => true, 'roles' => $admin],
            'review/reject' => [ReviewController::class, 'rejectSubmit', 'auth' => true, 'roles' => array_values(array_unique(array_merge($academic, $admin)))],
            'review/request-revision' => [ReviewController::class, 'requestRevisionSubmit', 'auth' => true, 'roles' => $reviewers],
            'review/assign' => [ReviewController::class, 'assignSubmit', 'auth' => true, 'roles' => $academic],
            'review/toggle-blind' => [ReviewController::class, 'toggleBlindSubmit', 'auth' => true, 'roles' => $academic],

            'search' => [SearchController::class, 'index', 'auth' => false],
            'search/api' => [SearchController::class, 'api', 'auth' => false],
            'search/suggest' => [SearchController::class, 'suggest', 'auth' => false],
            'search/rate' => [SearchController::class, 'rate', 'auth' => true],
            'search/bookmark' => [SearchController::class, 'bookmark', 'auth' => true],

            'reader' => [SearchController::class, 'reader', 'auth' => false],
            'file/manuscript' => [FileController::class, 'manuscript', 'auth' => false],
            'file/ethics' => [FileController::class, 'ethicsDoc', 'auth' => true],
            'file/plagiarism' => [FileController::class, 'plagiarismShot', 'auth' => true],

            'reports' => [ReportController::class, 'index', 'auth' => true, 'roles' => $reporters],
            'reports/export' => [ReportController::class, 'exportCsv', 'auth' => true, 'roles' => $reporters],
            'reports/print' => [ReportController::class, 'printView', 'auth' => true, 'roles' => $reporters],
            'reports/read-log' => [ReportController::class, 'readLog', 'auth' => true, 'roles' => $reporters],
            'reports/audit-trail' => [ReportController::class, 'auditTrail', 'auth' => true, 'roles' => $reporters],

            'settings' => [SettingsController::class, 'index', 'auth' => true, 'roles' => $admin],
            'settings/submit' => [SettingsController::class, 'submit', 'auth' => true, 'roles' => $admin],

            'notifications' => [NotificationController::class, 'index', 'auth' => true],
            'notifications/read' => [NotificationController::class, 'markRead', 'auth' => true],
            'notifications/read-all' => [NotificationController::class, 'markAllRead', 'auth' => true],
            'messages' => [NotificationController::class, 'messages', 'auth' => true],
        ];
    }

    public function dispatch(string $route): void
    {
        $route = trim($route, '/');
        if (!isset($this->routes[$route])) {
            http_response_code(404);
            view('errors/not_found', []);
            return;
        }

        $def = $this->routes[$route];
        if ((Auth::user()['role_slug'] ?? '') === Role::ACCOUNT_ADMIN
            && !in_array($route, ['admin/accounts', 'admin/accounts/create', 'admin/activity', 'dashboard',
                'login', 'login/submit', 'admin/login', 'admin/login/submit', 'logout', 'profile', 'profile/update', 'profile/photo',
                'password/change', 'password/change/submit'], true)) {
            http_response_code(403);
            view('errors/forbidden', ['message' => 'Admin access is limited to Librarian accounts and activity logs.']);
            return;
        }
        $publicRoutes = ['login', 'login/submit', 'admin/login', 'admin/login/submit', 'search', 'search/api', 'search/suggest', 'reader', 'file/manuscript'];
        if (!in_array($route, $publicRoutes, true)) {
            Auth::requireLogin();
            $user = Auth::user();
            $passwordRoutes = ['password/change', 'password/change/submit', 'logout'];
            if ($user && (int) ($user['must_change_password'] ?? 0) === 1 && !in_array($route, $passwordRoutes, true)) {
                redirect('password/change');
            }
            if (!empty($def['roles'])) {
                Auth::requireRole($def['roles']);
            }
        }

        [$class, $method] = $def;
        (new $class())->$method();
    }
}
