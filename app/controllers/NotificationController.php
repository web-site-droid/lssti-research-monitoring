<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Models\Notification;

final class NotificationController
{
    public function index(): void
    {
        $user = Auth::user();
        view('notifications/index', ['items' => Notification::forUser((int) $user['id'])]);
    }

    public function markRead(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $user = Auth::user();
        Notification::markRead($id, (int) $user['id']);
        if (($_GET['ajax'] ?? '') === '1' || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')) {
            json_response(['ok' => true]);
        }
        redirect('notifications');
    }

    public function markAllRead(): void
    {
        $user = Auth::user();
        Notification::markAllRead((int) $user['id']);
        if (($_GET['ajax'] ?? '') === '1' || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')) {
            json_response(['ok' => true]);
        }
        redirect('notifications');
    }

    public function messages(): void
    {
        $user = Auth::user();
        $role = $user['role_slug'];
        $sql = 'SELECT c.comment_text, c.section_label, c.created_at, c.visibility,
                       u.first_name, u.last_name, s.id AS study_id, s.short_title
                FROM review_comments c
                INNER JOIN users u ON u.id = c.reviewer_user_id
                INNER JOIN studies s ON s.id = c.study_id
                WHERE 1=1';
        $params = [];
        if ($role === 'research_adviser') {
            $sql .= ' AND (s.adviser_user_id = :uid OR EXISTS (
                SELECT 1 FROM review_assignments ra WHERE ra.study_id = s.id AND ra.reviewer_user_id = :uid2
            ))';
            $params['uid'] = $user['id'];
            $params['uid2'] = $user['id'];
        } elseif ($role === 'student') {
            $sql .= " AND s.submitter_user_id = :uid AND c.visibility = 'public'";
            $params['uid'] = $user['id'];
        }
        $sql .= ' ORDER BY c.created_at DESC LIMIT 50';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        view('messages/index', ['items' => $stmt->fetchAll()]);
    }
}
