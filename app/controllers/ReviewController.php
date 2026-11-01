<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\FileUpload;
use App\Helpers\Settings;
use App\Models\Notification;
use App\Models\Study;
use App\Models\User;

final class ReviewController
{
    public function index(): void
    {
        $user = Auth::user();
        $studies = Study::listForUser($user, []);
        $role = $user['role_slug'] ?? '';
        $adviserQueue = $role === 'research_adviser';
        $deanQueue = $role === 'dean';
        $queue = match ($role) {
            'research_adviser' => ['submitted', 'revision_requested', 'adviser_reviewed', 'pending', 'dean_review', 'under_review', 'library_review', 'admin_review', 'approved'],
            'dean' => ['dean_review'],
            'school_research_coordinator' => ['under_review', 'library_review', 'admin_review', 'approved', 'archived', 'dean_review', 'pending'],
            'school_administrator' => ['admin_review', 'library_review', 'under_review', 'approved', 'archived'],
            default => ['under_review'],
        };
        $pending = array_values(array_filter($studies, static fn ($s) => in_array($s['status'], $queue, true)));
        view('review/index', [
            'studies' => $pending,
            'adviserQueue' => $adviserQueue,
            'deanQueue' => $deanQueue,
            'adminQueue' => $role === 'school_administrator',
            'seeAllPapers' => $role === 'school_research_coordinator',
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function ethicsForm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !Study::canAccess($user, $study)) {
            redirect('review');
        }
        $role = $user['role_slug'] ?? '';
        $hideAuthors = (int) $study['double_blind'] === 1 && $role === 'research_adviser';
        view('review/ethics', [
            'study' => $study,
            'ethics' => Study::ethics($id),
            'comments' => Study::comments($id, false),
            'scores' => Study::scores($id),
            'plagiarism' => Study::latestPlagiarism($id, (int) ($study['current_version_id'] ?? 0)),
            'authorDisplay' => Study::formatAuthorList($id, $hideAuthors),
            'canApprove' => Study::canApprove($id),
            'panelists' => User::all('research_adviser'),
            'isAcademic' => in_array($role, ['school_research_coordinator', 'school_administrator'], true),
            'isDean' => $role === 'dean',
            'error' => flash('error'),
            'success' => flash('success'),
        ]);
    }

    public function ethicsSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !Study::canAccess($user, $study)) {
            redirect('review');
        }

        if (!in_array($user['role_slug'] ?? '', ['school_research_coordinator', 'school_administrator'], true)) {
            flash('error', 'Only the school research coordinator records these checks.');
            redirect('review/ethics', ['id' => $id]);
        }
        $format = $_POST['manuscript_format'] ?? 'pending';
        if (!in_array($format, ['passed', 'failed', 'pending'], true)) {
            $format = 'pending';
        }
        $formatRemarks = trim($_POST['manuscript_format_remarks'] ?? '');
        if ($format === 'failed' && $formatRemarks === '') {
            flash('error', 'Write a note when the manuscript format fails.');
            redirect('review/ethics', ['id' => $id]);
        }
        $fields = ['human_subjects', 'informed_consent', 'ethical_clearance', 'data_privacy_ra10173', 'coi_disclosed'];
        $data = [];
        foreach ($fields as $f) {
            $status = $_POST[$f] ?? 'pending';
            if (!in_array($status, ['passed', 'failed', 'pending'], true)) {
                $status = 'pending';
            }
            $data[$f] = $status;
            $data[$f . '_remarks'] = trim($_POST[$f . '_remarks'] ?? '');
            if ($status === 'failed' && $data[$f . '_remarks'] === '') {
                flash('error', 'Remarks are required for each failed ethics item.');
                redirect('review/ethics', ['id' => $id]);
            }
        }
        if ($data['ethical_clearance'] === 'passed' && trim($_POST['irb_code'] ?? '') === '' && empty($_FILES['irb_document']['name'])) {
            $existing = Study::ethics($id);
            if (empty($existing['irb_code']) && empty($existing['irb_document_path'])) {
                flash('error', 'IRB or ethical clearance code/document is required when ethical clearance is passed.');
                redirect('review/ethics', ['id' => $id]);
            }
        }
        $irbPath = Study::ethics($id)['irb_document_path'] ?? null;
        if (!empty($_FILES['irb_document']['name'])) {
            $irbPath = FileUpload::storeEthicsDoc($id, $_FILES['irb_document']);
        }

        $stmt = db()->prepare(
            'UPDATE ethics_checklists SET
             human_subjects = :hs, human_subjects_remarks = :hsr,
             informed_consent = :ic, informed_consent_remarks = :icr,
             ethical_clearance = :ec, irb_code = :code, irb_document_path = :path, ethical_clearance_remarks = :ecr,
             data_privacy_ra10173 = :dp, data_privacy_remarks = :dpr,
             coi_disclosed = :coi, coi_remarks = :coir,
             manuscript_format = :fmt, manuscript_format_remarks = :fmtr,
             last_updated_by = :uid, updated_at = NOW()
             WHERE study_id = :sid'
        );
        $stmt->execute([
            'hs' => $data['human_subjects'],
            'hsr' => $data['human_subjects_remarks'],
            'ic' => $data['informed_consent'],
            'icr' => $data['informed_consent_remarks'],
            'ec' => $data['ethical_clearance'],
            'code' => trim($_POST['irb_code'] ?? ''),
            'path' => $irbPath,
            'ecr' => $data['ethical_clearance_remarks'],
            'dp' => $data['data_privacy_ra10173'],
            'dpr' => $data['data_privacy_ra10173_remarks'],
            'coi' => $data['coi_disclosed'],
            'coir' => $data['coi_disclosed_remarks'],
            'fmt' => $format,
            'fmtr' => $formatRemarks,
            'uid' => $user['id'],
            'sid' => $id,
        ]);

        Notification::create((int) $study['submitter_user_id'], 'review', 'Ethics checklist updated', $study['title'], 'studies/view?id=' . $id);
        Audit::log('ethics.update', 'study', $id);
        flash('success', 'Ethics checklist saved.');
        redirect('review/ethics', ['id' => $id]);
    }

    public function commentSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !Study::canAccess($user, $study)) {
            redirect('review');
        }
        $section = trim($_POST['section_label'] ?? '');
        $text = trim($_POST['comment_text'] ?? '');
        if ($section === '' || $text === '') {
            flash('error', 'Section and comment are required.');
            redirect('review/ethics', ['id' => $id]);
        }
        $visibility = ($_POST['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
        $stmt = db()->prepare(
            'INSERT INTO review_comments (study_id, study_version_id, reviewer_user_id, section_label, comment_text, visibility)
             VALUES (:sid, :vid, :uid, :sec, :txt, :vis)'
        );
        $stmt->execute([
            'sid' => $id,
            'vid' => $study['current_version_id'],
            'uid' => $user['id'],
            'sec' => $section,
            'txt' => $text,
            'vis' => $visibility,
        ]);
        if ($visibility === 'public') {
            Notification::create((int) $study['submitter_user_id'], 'comment', 'New review comment', $section, 'studies/view?id=' . $id);
        }
        redirect('review/ethics', ['id' => $id]);
    }

    public function scoreSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !Study::canAccess($user, $study)) {
            redirect('review');
        }
        $score = (float) ($_POST['score'] ?? -1);
        if ($score < 0 || $score > 100) {
            flash('error', 'Score must be between 0 and 100.');
            redirect('review/ethics', ['id' => $id]);
        }
        $stmt = db()->prepare(
            'INSERT INTO review_scores (study_id, study_version_id, reviewer_user_id, score, remarks)
             VALUES (:sid, :vid, :uid, :score, :rem)'
        );
        $stmt->execute([
            'sid' => $id,
            'vid' => $study['current_version_id'],
            'uid' => $user['id'],
            'score' => $score,
            'rem' => trim($_POST['score_remarks'] ?? ''),
        ]);
        Notification::create((int) $study['submitter_user_id'], 'score', 'Review score posted', (string) $score, 'studies/view?id=' . $id);
        redirect('review/ethics', ['id' => $id]);
    }

    public function finishSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !Study::canAccess($user, $study) || $study['status'] !== 'submitted') {
            flash('error', 'This study is not waiting for an adviser review.');
            redirect('review');
        }
        if ((int) $study['adviser_user_id'] !== (int) $user['id']) {
            flash('error', 'Only the assigned adviser can finish this review.');
            redirect('review/ethics', ['id' => $id]);
        }
        $note = trim($_POST['adviser_note'] ?? '');
        if ($note === '') {
            flash('error', 'Write a note before you approve this study.');
            redirect('review/ethics', ['id' => $id]);
        }
        db()->prepare(
            'INSERT INTO review_comments (study_id, study_version_id, reviewer_user_id, section_label, comment_text, visibility)
             VALUES (:sid, :vid, :uid, :section, :comment, :vis)'
        )->execute([
            'sid' => $id,
            'vid' => $study['current_version_id'] ?: null,
            'uid' => $user['id'],
            'section' => 'Adviser approval',
            'comment' => $note,
            'vis' => 'public',
        ]);
        if (!Study::markAdviserReviewed($id)) {
            flash('error', 'This review could not be finished.');
            redirect('review/ethics', ['id' => $id]);
        }
        Notification::create(
            (int) $study['submitter_user_id'],
            'review',
            'Your research adviser approved this study. Send it to the dept research coordinator.',
            $note,
            'studies/view?id=' . $id
        );
        Audit::log('study.adviser_reviewed', 'study', $id);
        flash('success', 'Approved. The student sends this study to the dept research coordinator.');
        redirect('review');
    }

    public function deanApproveSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !Study::canAccess($user, $study) || $study['status'] !== 'dean_review') {
            flash('error', 'This study is not waiting for dean approval.');
            redirect('review');
        }
        if (!Study::sendToSchoolCoordinator($id)) {
            flash('error', 'This study could not be sent to the school research coordinator.');
            redirect('review');
        }
        foreach (User::all('school_research_coordinator') as $coord) {
            Notification::create(
                (int) $coord['id'],
                'review',
                'Study ready for school review',
                $study['short_title'],
                'review/ethics?id=' . $id
            );
        }
        Notification::create(
            (int) $study['submitter_user_id'],
            'review',
            'Dean approved — school review next',
            $study['short_title'],
            'studies/view?id=' . $id
        );
        Audit::log('study.dean_approved', 'study', $id);
        flash('success', 'Sent to the school research coordinator.');
        redirect('review');
    }

    public function plagiarismSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !in_array($user['role_slug'] ?? '', ['school_research_coordinator', 'school_administrator'], true)) {
            redirect('review');
        }
        $pct = (float) ($_POST['similarity_percentage'] ?? -1);
        if ($pct < 0 || $pct > 100) {
            flash('error', 'Invalid similarity percentage.');
            redirect('review/ethics', ['id' => $id]);
        }
        $shotPath = null;
        if (!empty($_FILES['screenshot']['name'])) {
            $img = FileUpload::validateImage($_FILES['screenshot']);
            if (!$img['ok']) {
                flash('error', $img['error']);
                redirect('review/ethics', ['id' => $id]);
            }
            $shotPath = FileUpload::storePlagiarismShot($id, $_FILES['screenshot']);
        }
        $stmt = db()->prepare(
            'INSERT INTO plagiarism_results (study_id, study_version_id, similarity_percentage, screenshot_path, recorded_by)
             VALUES (:sid, :vid, :pct, :shot, :uid)'
        );
        $stmt->execute([
            'sid' => $id,
            'vid' => $study['current_version_id'],
            'pct' => $pct,
            'shot' => $shotPath,
            'uid' => $user['id'],
        ]);
        Audit::log('plagiarism.record', 'study', $id, ['percent' => $pct]);
        flash('success', 'Plagiarism result recorded.');
        redirect('review/ethics', ['id' => $id]);
    }

    public function approveSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $check = Study::canApprove($id);
        if (!$check['ok']) {
            flash('error', $check['reason']);
            redirect('review');
        }
        if (!Study::approve($id)) {
            flash('error', 'Approval blocked.');
            redirect('review');
        }
        $study = Study::find($id);
        foreach (User::all('school_administrator') as $admin) {
            Notification::create(
                (int) $admin['id'],
                'review',
                'Study ready for school administrator',
                $study['short_title'],
                'review/ethics?id=' . $id
            );
        }
        Notification::create(
            (int) $study['submitter_user_id'],
            'review',
            'Sent to school administrator',
            'The school research coordinator cleared this study for the school administrator.',
            'studies/view?id=' . $id
        );
        Audit::log('study.send_admin', 'study', $id);
        flash('success', 'Sent to the school administrator.');
        redirect('review');
    }

    public function finalPublishSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $user = Auth::user();
        if (($user['role_slug'] ?? '') !== 'school_administrator') {
            flash('error', 'Only the school administrator can publish.');
            redirect('review');
        }
        $check = Study::canFinalPublish($id);
        if (!$check['ok']) {
            flash('error', $check['reason']);
            redirect('review');
        }
        if (!Study::finalPublish($id)) {
            flash('error', 'Publish blocked.');
            redirect('review');
        }
        $study = Study::find($id);
        Notification::create(
            (int) $study['submitter_user_id'],
            'approval',
            'Study published',
            'The school administrator published this study.',
            'studies/view?id=' . $id
        );
        Audit::log('study.approve', 'study', $id);
        flash('success', 'Published. The study is approved.');
        redirect('review');
    }

    public function rejectSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        db()->prepare("UPDATE studies SET status = 'archived', archived_at = NOW(), updated_at = NOW() WHERE id = :id")->execute(['id' => $id]);
        $study = Study::find($id);
        Notification::create((int) $study['submitter_user_id'], 'rejection', 'Study not approved', $study['title'], 'studies/view?id=' . $id);
        Audit::log('study.reject', 'study', $id);
        redirect('review');
    }

    public function requestRevisionSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        db()->prepare("UPDATE studies SET status = 'revision_requested', updated_at = NOW() WHERE id = :id")->execute(['id' => $id]);
        $study = Study::find($id);
        Notification::create((int) $study['submitter_user_id'], 'revision', 'Revision requested', $study['title'], 'studies/revise?id=' . $id);
        Audit::log('study.revision_request', 'study', $id);
        redirect('review/ethics', ['id' => $id]);
    }

    public function assignSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $reviewerId = (int) ($_POST['reviewer_user_id'] ?? 0);
        $type = $_POST['assignment_type'] ?? 'panelist';
        if (!in_array($type, ['research_adviser', 'panelist'], true)) {
            $type = 'panelist';
        }
        $stmt = db()->prepare(
            'INSERT INTO review_assignments (study_id, reviewer_user_id, assignment_type, assigned_by)
             VALUES (:sid, :rid, :type, :by)
             ON DUPLICATE KEY UPDATE assigned_at = NOW()'
        );
        $stmt->execute([
            'sid' => $id,
            'rid' => $reviewerId,
            'type' => $type,
            'by' => Auth::user()['id'],
        ]);
        Notification::create($reviewerId, 'assignment', 'Assigned to review study', 'Study #' . $id, 'review/ethics?id=' . $id);
        Audit::log('review.assign', 'study', $id);
        redirect('review/ethics', ['id' => $id]);
    }

    public function toggleBlindSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $val = isset($_POST['double_blind']) ? 1 : 0;
        db()->prepare('UPDATE studies SET double_blind = :v, updated_at = NOW() WHERE id = :id')->execute(['v' => $val, 'id' => $id]);
        Audit::log('study.toggle_blind', 'study', $id, ['double_blind' => $val]);
        redirect('review/ethics', ['id' => $id]);
    }
}
