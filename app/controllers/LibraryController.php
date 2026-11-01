<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Role;
use App\Models\Notification;
use App\Models\Study;
use App\Models\User;

final class LibraryController
{
    public function index(): void
    {
        redirect('dashboard');
    }

    public function review(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !Study::canAccess($user, $study)) {
            flash('error', 'That study is not ready for review.');
            redirect('dashboard');
        }
        view('library/review', [
            'contentWide' => true,
            'study' => $study,
            'comments' => Study::comments($id, false),
            'authorDisplay' => Study::formatAuthorList($id, false),
            'isLibrarianGate' => ($user['role_slug'] ?? '') === Role::LIBRARIAN || ($study['status'] ?? '') === 'library_review',
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function commentSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !Study::canAccess($user, $study)) {
            redirect('dashboard');
        }
        $comment = trim($_POST['comment'] ?? '');
        if ($comment === '') {
            flash('error', 'Write a review note before saving.');
            redirect('library/review', ['id' => $id]);
        }
        $isLibrarian = ($user['role_slug'] ?? '') === Role::LIBRARIAN || ($study['status'] ?? '') === 'library_review';
        $section = $isLibrarian ? 'School administrator review' : 'Department research coordinator review';
        db()->prepare(
            'INSERT INTO review_comments (study_id, study_version_id, reviewer_user_id, section_label, comment_text, visibility)
             VALUES (:sid, :vid, :uid, :section, :comment, :vis)'
        )->execute([
            'sid' => $id,
            'vid' => $study['current_version_id'] ?: null,
            'uid' => $user['id'],
            'section' => $section,
            'comment' => $comment,
            'vis' => 'public',
        ]);
        Notification::create(
            (int) $study['submitter_user_id'],
            'library',
            $isLibrarian ? 'Review saved' : 'Department review saved',
            $isLibrarian
                ? 'Your study is ready for the school administrator.'
                : 'The dept research coordinator reviewed your study.',
            'studies/view?id=' . $id
        );
        Audit::log('library.comment', 'study', $id);
        flash('success', $isLibrarian
            ? 'Review saved. Submit the study to the school administrator when ready.'
            : 'Review saved. Submit the study to the dean when the review is complete.');
        redirect('library/review', ['id' => $id, 'saved' => 'note']);
    }

    public function approveSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !Study::canAccess($user, $study)) {
            redirect('dashboard');
        }

        $role = $user['role_slug'] ?? '';
        $isLibrarian = $role === Role::LIBRARIAN || ($study['status'] ?? '') === 'library_review';

        if ($isLibrarian) {
            if ($study['status'] !== 'library_review' || !Study::hasLibraryReview($id)) {
                flash('error', 'Save a review note before submitting to the school administrator.');
                redirect('library/review', ['id' => $id]);
            }
            if (!Study::sendToSchoolAdmin($id)) {
                flash('error', 'This study could not be sent to the school administrator.');
                redirect('library/review', ['id' => $id]);
            }
            foreach (User::all(Role::SCHOOL_ADMINISTRATOR) as $admin) {
                Notification::create(
                    (int) $admin['id'],
                    'library',
                    'Study ready for school admin publish',
                    $study['short_title'],
                    'review'
                );
            }
            Notification::create(
                (int) $study['submitter_user_id'],
                'library',
                'Study with school administrator',
                $study['short_title'],
                'studies/view?id=' . $id
            );
            Audit::log('library.approve', 'study', $id);
            flash('success', 'Submitted to the school administrator.');
            redirect('dashboard');
        }

        if ($study['status'] !== 'pending' || !Study::hasLibraryReview($id)) {
            flash('error', 'Save a review note before submitting this study to the dean.');
            redirect('library/review', ['id' => $id]);
        }
        if (!Study::sendToDean($id)) {
            flash('error', 'This study could not be sent to the dean.');
            redirect('library/review', ['id' => $id]);
        }
        foreach (User::all('dean') as $dean) {
            Notification::create(
                (int) $dean['id'],
                'library',
                'Study ready for dean review',
                $study['short_title'],
                'review'
            );
        }
        Notification::create(
            (int) $study['submitter_user_id'],
            'library',
            'Study with the dean',
            $study['short_title'],
            'studies/view?id=' . $id
        );
        Audit::log('library.approve', 'study', $id);
        flash('success', 'Submitted to the dean.');
        redirect('dashboard');
    }

    public function acceptSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $study = Study::find($id);
        if (!$study || $study['status'] !== 'approved') {
            redirect('dashboard');
        }
        db()->prepare(
            'UPDATE studies SET library_accepted_at = NOW(), library_retracted_at = NULL, updated_at = NOW() WHERE id = :id'
        )->execute(['id' => $id]);
        Notification::create(
            (int) $study['submitter_user_id'],
            'library',
            'Manuscript accepted in library',
            $study['short_title'],
            'studies/view?id=' . $id
        );
        Audit::log('library.accept', 'study', $id);
        flash('success', 'Manuscript accepted into the library.');
        redirect('dashboard');
    }

    public function retractSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $study = Study::find($id);
        if (!$study) {
            redirect('dashboard');
        }
        db()->prepare(
            'UPDATE studies SET library_retracted_at = NOW(), updated_at = NOW() WHERE id = :id'
        )->execute(['id' => $id]);
        Notification::create(
            (int) $study['submitter_user_id'],
            'library',
            'Manuscript retracted from library',
            $study['short_title'],
            'studies/view?id=' . $id
        );
        Audit::log('library.retract', 'study', $id);
        flash('success', 'Manuscript retracted.');
        redirect('dashboard');
    }

    public function metadataSubmit(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !Study::canAccess($user, $study)) {
            flash('error', 'That study is not open for catalog changes.');
            redirect('dashboard');
        }
        $type = (string) ($_POST['study_type'] ?? '');
        $year = trim((string) ($_POST['school_year'] ?? ''));
        $abstract = trim((string) ($_POST['abstract'] ?? ''));
        $keywords = \App\Helpers\StudyValidator::parseKeywords((string) ($_POST['keywords'] ?? ''));
        if (!isset(study_type_options()[$type]) || $year === '' || $abstract === '' || $keywords['errors']) {
            flash('error', $keywords['errors'][0] ?? 'Enter the document type, school year, and abstract.');
            redirect('library/review', ['id' => $id]);
        }
        Study::updateCatalog($id, $abstract, $type, $year);
        Study::saveKeywords($id, $keywords['keywords']);
        Audit::log('library.metadata', 'study', $id);
        flash('success', 'Catalog details saved.');
        redirect('library/review', ['id' => $id]);
    }
}
