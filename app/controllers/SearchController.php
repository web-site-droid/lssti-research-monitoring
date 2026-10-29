<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Program;
use App\Models\ReadLog;
use App\Models\Study;
use App\Models\User;

final class SearchController
{
    public function landing(): void
    {
        $user = \App\Helpers\Auth::user();
        $programs = Program::directorAdded();
        $advisers = User::all('research_adviser');
        view('home/index', [
            'user' => $user,
            'programs' => $programs,
            'advisers' => $advisers,
            'schoolYears' => Study::schoolYearOptions(),
            'studentLocked' => false,
            'studentProgramId' => 0,
            'studentProgramLabel' => '',
        ], 'landing');
    }

    public function index(): void
    {
        $programs = Program::directorAdded();
        $advisers = User::all('research_adviser');
        view('search/index', [
            'programs' => $programs,
            'advisers' => $advisers,
            'schoolYears' => Study::schoolYearOptions(),
            'studentLocked' => false,
            'studentProgramId' => 0,
            'studentProgramLabel' => '',
        ]);
    }

    public function api(): void
    {
        $filters = [
            'q' => $_GET['q'] ?? '',
            'program_id' => $_GET['program_id'] ?? '',
            'school_year' => $_GET['school_year'] ?? '',
            'adviser_user_id' => $_GET['adviser_user_id'] ?? '',
            'study_type' => $_GET['study_type'] ?? '',
            'keyword' => $_GET['keyword'] ?? '',
            'date_field' => $_GET['date_field'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $sort = $_GET['sort'] ?? 'relevance';
        $user = \App\Helpers\Auth::user();
        // Students may search every published paper. The file check still blocks full manuscripts.
        $featured = trim($filters['q']) === ''
            && ($filters['school_year'] ?? '') === ''
            && ($filters['adviser_user_id'] ?? '') === ''
            && ($filters['study_type'] ?? '') === ''
            && ($filters['keyword'] ?? '') === ''
            && ($filters['date_from'] ?? '') === ''
            && ($filters['date_to'] ?? '') === ''
            && ($filters['program_id'] ?? '') === '';
        $results = Study::searchApproved($filters, $sort);
        $stmt = db()->prepare(
            'INSERT INTO search_logs (user_id, query_text, filters_json, result_count, searched_at)
             VALUES (:uid, :q, :f, :c, NOW())'
        );
        $stmt->execute([
            'uid' => $user ? (int) $user['id'] : null,
            'q' => substr(trim($filters['q']), 0, 255),
            'f' => json_encode($filters),
            'c' => count($results),
        ]);

        $mine = [];
        if ($user && ($user['role_slug'] ?? '') === 'student') {
            $mine = Study::userRatingsMap((int) $user['id'], array_map(static fn ($row) => (int) $row['id'], $results));
        }
        $savedIds = $user ? array_flip(Study::savedIds((int) $user['id'])) : [];
        $items = [];
        foreach ($results as $row) {
            $id = (int) $row['id'];
            $authors = Study::authors($id);
            $fullAccess = Study::canOpenFullManuscript($user, $row);
            $items[] = [
                'id' => $id,
                'title' => $row['title'],
                'short_title' => $row['short_title'],
                'program_code' => $row['program_code'],
                'school_year' => $row['school_year'],
                'study_type' => $row['study_type'],
                'study_type_label' => study_type_label($row['study_type'] ?? ''),
                'authors' => Study::formatAuthorList($id, false),
                'abstract' => $row['abstract'] ?? '',
                'objectives' => $row['research_objectives'] ?? '',
                'stars' => (int) ($row['star_total'] ?? 0),
                'star_avg' => round((float) ($row['star_avg'] ?? 0), 1),
                'my_stars' => (int) ($mine[$id] ?? 0),
                'version_id' => $fullAccess ? (int) ($row['current_version_id'] ?? 0) : 0,
                'full_access' => $fullAccess,
                'access_note' => $fullAccess
                    ? ''
                    : (match ($user['role_slug'] ?? '') {
                        'student' => 'Abstract only — studies from other departments show the title and abstract.',
                        'research_adviser' => 'Abstract only — full files are for your department. Other departments show the title and abstract.',
                        'dept_research_coordinator' => 'Abstract only — full files are for your department; other departments show title and abstract.',
                        'dean' => 'Abstract only — full files are for your department; other departments show title and abstract.',
                        default => 'Abstract only — full paper is limited to the ' . ($row['program_code'] ?? 'owning') . ' department.',
                    }),
                'filename' => $fullAccess ? ($row['original_filename'] ?? '') : '',
                'saved' => isset($savedIds[$id]),
                'citations' => Study::citations($row, $authors),
            ];
        }
        json_response(['results' => $items, 'featured' => $featured]);
    }

    public function suggest(): void
    {
        $term = $_GET['term'] ?? '';
        json_response(['suggestions' => Study::suggest($term, 10, 0)]);
    }

    public function reader(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $study = Study::find($id);
        $user = \App\Helpers\Auth::user();
        $fullAccess = $user ? Study::canOpenFullManuscript($user, $study ?: []) : false;
        if (!$study || (!$fullAccess && ($study['status'] ?? '') !== 'approved')) {
            http_response_code(403);
            view('errors/forbidden', ['message' => 'This study is not available for reading.']);
            return;
        }
        $versionId = (int) ($study['current_version_id'] ?? 0);
        $versions = Study::versions($id);
        $version = null;
        foreach ($versions as $v) {
            if ((int) $v['id'] === $versionId) {
                $version = $v;
                break;
            }
        }
        if ($fullAccess && !$version) {
            view('errors/forbidden', ['message' => 'No manuscript file available.']);
            return;
        }
        if ($fullAccess && $user && $version) {
            ReadLog::record((int) $user['id'], $id, $versionId);
        }
        $ext = $version ? strtolower(pathinfo($version['original_filename'], PATHINFO_EXTENSION)) : '';
        view('search/reader', [
            'study' => $study,
            'version' => $version,
            'ext' => $ext,
            'fullAccess' => $fullAccess,
            'keywords' => Study::keywords($id),
            'authorDisplay' => Study::formatAuthorList($id, false),
            'rating' => Study::ratingSummary($id),
            'myStars' => $user ? Study::userRating($id, (int) $user['id']) : 0,
            'success' => flash('success'),
        ]);
    }

    public function rate(): void
    {
        \App\Helpers\Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $stars = (int) ($_POST['stars'] ?? 0);
        $ajax = ($_POST['ajax'] ?? '') === '1';
        $study = Study::find($id);
        if (!$study || $study['status'] !== 'approved' || $stars < 1 || $stars > 5) {
            if ($ajax) {
                json_response(['ok' => false, 'error' => 'Choose 1 to 5 stars on a published study.'], 422);
            }
            flash('error', 'Choose 1 to 5 stars on a published study.');
            redirect('reader', ['id' => $id]);
        }
        $user = \App\Helpers\Auth::user();
        if ($ajax && ($user['role_slug'] ?? '') !== 'student') {
            json_response(['ok' => false, 'error' => 'Only students can rate a study.'], 403);
        }
        Study::rate($id, (int) $user['id'], $stars);
        if ($ajax) {
            $summary = Study::ratingSummary($id);
            json_response([
                'ok' => true,
                'my_stars' => $stars,
                'stars' => $summary['star_total'],
                'star_avg' => round($summary['star_avg'], 1),
            ]);
        }
        flash('success', 'Your stars were saved.');
        redirect('reader', ['id' => $id]);
    }

    public function bookmark(): void
    {
        \App\Helpers\Csrf::requireValid();
        $id = (int) ($_POST['study_id'] ?? 0);
        $ajax = ($_POST['ajax'] ?? '') === '1';
        $study = Study::find($id);
        $user = \App\Helpers\Auth::user();
        if (!$study || $study['status'] !== 'approved' || !$user) {
            if ($ajax) {
                json_response(['ok' => false, 'error' => 'Only a published study can be saved.'], 422);
            }
            flash('error', 'Only a published study can be saved.');
            redirect('profile');
        }
        $saved = Study::toggleBookmark((int) $user['id'], $id);
        if ($ajax) {
            json_response(['ok' => true, 'saved' => $saved]);
        }
        flash('success', $saved ? 'Saved to your bookmarks.' : 'Removed from your bookmarks.');
        redirect('profile');
    }
}
