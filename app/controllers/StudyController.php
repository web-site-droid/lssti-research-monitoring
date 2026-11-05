<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Role;
use App\Helpers\FileUpload;
use App\Helpers\StorageFiles;
use App\Helpers\StudyValidator;
use App\Helpers\SupabaseSync;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Study;
use App\Models\User;

final class StudyController
{
    public function index(): void
    {
        $user = Auth::user();
        $studies = Study::listForUser($user, ['status' => $_GET['status'] ?? null]);
        view('studies/index', ['studies' => $studies, 'user' => $user]);
    }

    public function folders(): void
    {
        $publishedOnly = true;
        $sql = "SELECT s.id, s.title, s.short_title, s.status, s.program_id, s.current_version_id,
                s.adviser_user_id, s.submitter_user_id, s.abstract, s.research_objectives,
                s.school_year, s.study_type, s.submitted_at, s.created_at, p.code AS program_code,
                (
                    SELECT GROUP_CONCAT(
                        CONCAT(a.last_name, ', ', a.first_name,
                            IF(a.middle_name IS NULL OR a.middle_name = '', '', CONCAT(' ', LEFT(a.middle_name, 1), '.')))
                        ORDER BY a.sort_order SEPARATOR '; '
                    )
                    FROM study_authors a
                    WHERE a.study_id = s.id
                ) AS authors
                FROM studies s
                INNER JOIN programs p ON p.id = s.program_id
                WHERE s.status = 'approved'
                ORDER BY COALESCE(s.submitted_at, s.created_at) DESC";
        view('studies/folders', [
            'studies' => db()->query($sql)->fetchAll(),
            'programs' => Program::active(),
            'publishedOnly' => $publishedOnly,
        ]);
    }

    public function track(): void
    {
        $user = Auth::user();
        $ownUploads = ($user['role_slug'] ?? '') === 'research_adviser';
        $waiting = [];
        if (!$ownUploads) {
            $stmt = db()->prepare(
                "SELECT s.id, s.short_title, s.title, s.status, s.study_type, p.code AS program_code,
                        a.first_name AS adviser_first, a.last_name AS adviser_last
                 FROM studies s
                 INNER JOIN programs p ON p.id = s.program_id
                 LEFT JOIN users a ON a.id = s.adviser_user_id
                 WHERE s.submitter_user_id = :uid
                   AND s.status IN ('submitted', 'revision_requested', 'adviser_reviewed')
                 ORDER BY s.updated_at DESC"
            );
            $stmt->execute(['uid' => (int) $user['id']]);
            $waiting = $stmt->fetchAll();
        }
        $tab = ($_GET['tab'] ?? '') === 'waiting' ? 'waiting' : 'review';
        view('studies/track', [
            'studies' => $ownUploads ? Study::listSubmittedBy((int) $user['id']) : Study::listForUser($user, []),
            'adviserTrack' => $ownUploads,
            'waitingStudies' => $waiting,
            'trackTab' => $tab,
            'user' => $user,
        ]);
    }

    public function approval(): void
    {
        redirect('track', ['tab' => 'waiting']);
    }

    public function createForm(): void
    {
        view('studies/create', $this->uploadFormData([
            'error' => flash('error'),
        ]));
    }

    public function createSubmit(): void
    {
        if (request_exceeded_post_max()) {
            flash('error', FileUpload::sizeError());
            redirect('studies/create');
        }
        Csrf::requireValid();
        $user = Auth::user();
        $this->forceAdviserLibraryPass();
        $this->forceUploaderDepartment();
        $authors = $this->parseAuthors($_POST);
        $kw = StudyValidator::parseKeywords($_POST['keywords'] ?? '');
        $errors = array_merge(
            StudyValidator::validateMetadata($_POST),
            StudyValidator::validateAuthors($authors),
            $kw['errors']
        );
        $fileCheck = $this->incomingManuscript(true);
        if (!$fileCheck['ok']) {
            $errors[] = $fileCheck['error'];
        }
        $this->forceStudentAdviserPass();
        $passTo = ($_POST['pass_to'] ?? '') === 'library' ? 'library' : (($_POST['pass_to'] ?? '') === 'teacher' ? 'teacher' : '');
        if ($passTo === 'teacher') {
            $adviserError = $this->adviserDepartmentError((int) ($_POST['program_id'] ?? 0), (int) ($_POST['adviser_user_id'] ?? 0));
            if ($adviserError !== '') {
                $errors[] = $adviserError;
            }
        }
        if ($errors) {
            $errors = array_values(array_unique($errors));
            remember_form([
                'title' => (string) ($_POST['title'] ?? ''),
                'short_title' => (string) ($_POST['short_title'] ?? ''),
                'abstract' => (string) ($_POST['abstract'] ?? ''),
                'keywords' => (string) ($_POST['keywords'] ?? ''),
                'study_type' => (string) ($_POST['study_type'] ?? ''),
                'pass_to' => (string) ($_POST['pass_to'] ?? ''),
                'program_id' => (string) ($_POST['program_id'] ?? ''),
                'adviser_user_id' => (string) ($_POST['adviser_user_id'] ?? ''),
                'school_year' => (string) ($_POST['school_year'] ?? ''),
                'author_first' => $_POST['author_first'] ?? [],
                'author_middle' => $_POST['author_middle'] ?? [],
                'author_last' => $_POST['author_last'] ?? [],
            ]);
            flash('error', implode(' ', $errors));
            redirect('studies/create');
        }

        $meta = [
            'title' => trim($_POST['title']),
            'short_title' => trim($_POST['short_title']),
            'abstract' => trim($_POST['abstract']),
            'research_objectives' => '',
            'study_type' => $_POST['study_type'],
            'program_id' => (int) $_POST['program_id'],
            'adviser_user_id' => $passTo === 'teacher'
                ? (int) $_POST['adviser_user_id']
                : (($user['role_slug'] ?? '') === 'research_adviser' ? (int) $user['id'] : null),
            'pass_to' => $passTo === 'library' ? 'library' : 'teacher',
            'school_year' => trim($_POST['school_year']),
            'defense_date' => null,
            'coi_statement' => '',
        ];
        $studyId = Study::createDraft((int) $user['id'], $meta, $authors, $kw['keywords']);
        try {
            $path = FileUpload::storeIncoming($studyId, 1, $fileCheck, $_FILES['manuscript'] ?? [], (int) $user['id'], '_full');
            Study::addVersion(
                $studyId,
                (int) $user['id'],
                ['name' => (string) ($fileCheck['name'] ?? ($_FILES['manuscript']['name'] ?? 'manuscript.pdf'))],
                $path,
                (string) ($fileCheck['mime'] ?? 'application/octet-stream'),
                (int) ($fileCheck['size'] ?? ($_FILES['manuscript']['size'] ?? 0)),
                'Initial submission'
            );
        } catch (\Throwable $e) {
            try {
                Study::deleteOwned($studyId);
            } catch (\Throwable $ignored) {
            }
            flash('error', 'Manuscript: the file could not be saved. Try a PDF or DOCX under ' . FileUpload::maxMb() . ' MB.');
            redirect('studies/create');
        }
        SupabaseSync::mirrorFileToStorage($path, 'study-' . $studyId . '/full-' . basename($path));

        if (($_POST['intent'] ?? 'draft') !== 'submit') {
            Audit::log('study.create_draft', 'study', $studyId);
            flash('success', 'Saved as a draft on your profile. It was not submitted for review.');
            redirect('profile');
        }

        $this->deliverStudy($studyId, $meta['pass_to'], $meta['adviser_user_id'], $meta['title']);
        redirect('studies/view', ['id' => $studyId]);
    }

    public function editForm(): void
    {
        $study = $this->editableStudy((int) ($_GET['id'] ?? 0));
        $posted = form_old();
        if ($posted === []) {
            $authors = Study::authors((int) $study['id']);
            $posted = [
                'title' => $study['title'],
                'short_title' => $study['short_title'],
                'abstract' => $study['abstract'],
                'research_objectives' => $study['research_objectives'] ?? '',
                'keywords' => implode(', ', Study::keywords((int) $study['id'])),
                'study_type' => $study['study_type'],
                'pass_to' => $study['pass_to'] ?? 'teacher',
                'program_id' => (string) $study['program_id'],
                'adviser_user_id' => (string) $study['adviser_user_id'],
                'school_year' => $study['school_year'],
                'defense_date' => !empty($study['defense_date']) ? substr((string) $study['defense_date'], 0, 10) : '',
                'coi_statement' => $study['coi_statement'],
                'author_first' => array_column($authors, 'first_name') ?: [''],
                'author_middle' => array_column($authors, 'middle_name') ?: [''],
                'author_last' => array_column($authors, 'last_name') ?: [''],
            ];
        }
        view('studies/create', $this->uploadFormData([
            'error' => flash('error'),
            'old' => $posted,
            'editingId' => (int) $study['id'],
        ]));
    }

    public function editSubmit(): void
    {
        if (request_exceeded_post_max()) {
            flash('error', FileUpload::sizeError());
            redirect('studies/edit', ['id' => (int) ($_POST['id'] ?? 0)]);
        }
        Csrf::requireValid();
        $this->forceAdviserLibraryPass();
        $this->forceUploaderDepartment();
        $study = $this->editableStudy((int) ($_POST['id'] ?? 0));
        $studyId = (int) $study['id'];
        $user = Auth::user();
        $authors = $this->parseAuthors($_POST);
        $kw = StudyValidator::parseKeywords($_POST['keywords'] ?? '');
        $errors = array_merge(
            StudyValidator::validateMetadata($_POST),
            StudyValidator::validateAuthors($authors),
            $kw['errors']
        );
        $fileCheck = $this->incomingManuscript(false);
        $hasFull = empty($fileCheck['skip']);
        if ($hasFull && !$fileCheck['ok']) {
            $errors[] = $fileCheck['error'];
        }
        $this->forceStudentAdviserPass();
        $passTo = ($_POST['pass_to'] ?? '') === 'library' ? 'library' : (($_POST['pass_to'] ?? '') === 'teacher' ? 'teacher' : '');
        if ($passTo === 'teacher') {
            $adviserError = $this->adviserDepartmentError((int) ($_POST['program_id'] ?? 0), (int) ($_POST['adviser_user_id'] ?? 0));
            if ($adviserError !== '') {
                $errors[] = $adviserError;
            }
        }
        if ($errors) {
            $errors = array_values(array_unique($errors));
            remember_form([
                'title' => (string) ($_POST['title'] ?? ''),
                'short_title' => (string) ($_POST['short_title'] ?? ''),
                'abstract' => (string) ($_POST['abstract'] ?? ''),
                'keywords' => (string) ($_POST['keywords'] ?? ''),
                'study_type' => (string) ($_POST['study_type'] ?? ''),
                'pass_to' => (string) ($_POST['pass_to'] ?? ''),
                'program_id' => (string) ($_POST['program_id'] ?? ''),
                'adviser_user_id' => (string) ($_POST['adviser_user_id'] ?? ''),
                'school_year' => (string) ($_POST['school_year'] ?? ''),
                'author_first' => $_POST['author_first'] ?? [],
                'author_middle' => $_POST['author_middle'] ?? [],
                'author_last' => $_POST['author_last'] ?? [],
            ]);
            flash('error', implode(' ', $errors));
            redirect('studies/edit', ['id' => $studyId]);
        }

        $meta = [
            'title' => trim($_POST['title']),
            'short_title' => trim($_POST['short_title']),
            'abstract' => trim($_POST['abstract']),
            'research_objectives' => (string) ($study['research_objectives'] ?? ''),
            'study_type' => $_POST['study_type'],
            'program_id' => (int) $_POST['program_id'],
            'adviser_user_id' => $passTo === 'teacher'
                ? (int) $_POST['adviser_user_id']
                : (($user['role_slug'] ?? '') === 'research_adviser' ? (int) $user['id'] : null),
            'pass_to' => $passTo === 'library' ? 'library' : 'teacher',
            'school_year' => trim($_POST['school_year']),
            'defense_date' => $study['defense_date'] ?? null,
            'coi_statement' => (string) ($study['coi_statement'] ?? ''),
        ];
        Study::updateDetails($studyId, $meta, $authors, $kw['keywords']);
        if ($hasFull && $fileCheck['ok']) {
            $versions = Study::versions($studyId);
            $next = ((int) ($versions[0]['version_number'] ?? 0)) + 1;
            try {
                $path = FileUpload::storeIncoming($studyId, $next, $fileCheck, $_FILES['manuscript'] ?? [], (int) $user['id'], '_full');
                Study::addVersion(
                    $studyId,
                    (int) $user['id'],
                    ['name' => (string) ($fileCheck['name'] ?? ($_FILES['manuscript']['name'] ?? 'manuscript.pdf'))],
                    $path,
                    (string) ($fileCheck['mime'] ?? 'application/octet-stream'),
                    (int) ($fileCheck['size'] ?? ($_FILES['manuscript']['size'] ?? 0)),
                    'Updated draft'
                );
            } catch (\Throwable $e) {
                flash('error', 'Manuscript: the file could not be saved. Try a PDF or DOCX under ' . FileUpload::maxMb() . ' MB.');
                redirect('studies/edit', ['id' => $studyId]);
            }
        }
        if (($_POST['intent'] ?? 'draft') === 'submit') {
            $fresh = Study::find($studyId);
            if (!$fresh || empty($fresh['current_version_id'])) {
                flash('error', 'Upload a manuscript before submitting.');
                redirect('studies/edit', ['id' => $studyId]);
            }
            Audit::log('study.update_draft', 'study', $studyId);
            $this->deliverStudy($studyId, $meta['pass_to'], $meta['adviser_user_id'], $meta['title']);
            redirect('studies/view', ['id' => $studyId]);
        }
        Audit::log('study.update_draft', 'study', $studyId);
        flash('success', 'Draft updated. It is still not submitted.');
        redirect('profile');
    }

    public function deletePublishedSubmit(): void
    {
        Auth::requireRole(Role::SCHOOL_ADMINISTRATOR);
        Csrf::requireValid();
        try {
            Study::deletePublished((int) ($_POST['study_id'] ?? 0), Auth::user());
            flash('success', 'Published study deleted.');
        } catch (\PDOException $error) {
            flash('error', 'The study could not be deleted. Its records have been preserved.');
        } catch (\RuntimeException $error) {
            flash('error', $error->getMessage());
        }
        redirect('study');
    }

    public function deleteSubmit(): void
    {
        Csrf::requireValid();
        $study = $this->editableStudy((int) ($_POST['id'] ?? 0));
        Study::deleteOwned((int) $study['id']);
        Audit::log('study.delete_draft', 'study', (int) $study['id']);
        flash('success', 'Study deleted.');
        redirect('profile');
    }

    public function view(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !Study::canAccess($user, $study)) {
            http_response_code(403);
            view('errors/forbidden', ['message' => 'Cannot view this study.']);
            return;
        }
        $hideAuthors = (int) $study['double_blind'] === 1 && $user['role_slug'] === 'research_adviser';
        $showReaders = Role::canSeeArticleReaders($user, $study);
        view('studies/view', [
            'study' => $study,
            'authors' => $hideAuthors ? [] : Study::authors($id),
            'keywords' => Study::keywords($id),
            'versions' => Study::versions($id),
            'comments' => Study::comments($id, $user['role_slug'] === 'student'),
            'scores' => Study::scores($id),
            'ethics' => Study::ethics($id),
            'plagiarism' => Study::latestPlagiarism($id, (int) ($study['current_version_id'] ?? 0)),
            'authorDisplay' => Study::formatAuthorList($id, $hideAuthors),
            'user' => $user,
            'success' => flash('success'),
            'error' => flash('error'),
            'showReaders' => $showReaders,
            'readers' => $showReaders ? \App\Models\ReadLog::latestForStudy($id) : [],
            'downloaders' => $showReaders ? \App\Models\DownloadLog::latestForStudy($id) : [],
            'fileMissing' => $this->currentFileMissing($study),
            'canReplaceFile' => Study::canReplaceManuscript($user, $study) && $this->currentFileMissing($study),
        ]);
    }

    public function uploadPart(): void
    {
        if (!Csrf::validate(is_string($_POST['_csrf'] ?? null) ? (string) $_POST['_csrf'] : null)) {
            json_response(['ok' => false, 'error' => 'Invalid security token. Refresh and try again.'], 403);
        }
        $user = Auth::user();
        try {
            json_response(FileUpload::receivePart((int) ($user['id'] ?? 0)));
        } catch (\Throwable $e) {
            json_response(['ok' => false, 'error' => 'Manuscript: the file could not be saved. Try again.'], 500);
        }
    }

    public function replaceFile(): void
    {
        if (request_exceeded_post_max()) {
            flash('error', FileUpload::sizeError());
            redirect('dashboard');
        }
        Csrf::requireValid();
        $id = (int) ($_POST['id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || !Study::canReplaceManuscript($user, $study) || !$this->currentFileMissing($study)) {
            flash('error', 'This manuscript cannot be replaced.');
            redirect('reader', ['id' => $id]);
        }
        $fileCheck = $this->incomingManuscript(true);
        if (!$fileCheck['ok']) {
            flash('error', (string) $fileCheck['error']);
            redirect('reader', ['id' => $id]);
        }
        $versions = Study::versions($id);
        $currentId = (int) ($study['current_version_id'] ?? 0);
        $version = null;
        foreach ($versions as $row) {
            if ((int) $row['id'] === $currentId) {
                $version = $row;
                break;
            }
        }
        if ($version === null && $versions !== []) {
            $version = $versions[0];
        }
        try {
            $fileName = ['name' => (string) ($fileCheck['name'] ?? 'manuscript.pdf')];
            if ($version === null) {
                $path = FileUpload::storeIncoming($id, 1, $fileCheck, $_FILES['manuscript'] ?? [], (int) $user['id'], '_full');
                Study::addVersion(
                    $id,
                    (int) $user['id'],
                    $fileName,
                    $path,
                    (string) ($fileCheck['mime'] ?? 'application/octet-stream'),
                    (int) ($fileCheck['size'] ?? 0),
                    'Replaced missing file'
                );
            } else {
                $path = (string) ($version['stored_path'] ?? '');
                if ($path === '') {
                    $path = FileUpload::storeIncoming($id, max(1, (int) ($version['version_number'] ?? 1)), $fileCheck, $_FILES['manuscript'] ?? [], (int) $user['id'], '_full');
                } else {
                    FileUpload::writeIncomingTo($path, $fileCheck, $_FILES['manuscript'] ?? [], (int) $user['id']);
                }
                $stmt = db()->prepare(
                    'UPDATE study_versions
                     SET stored_path = :path, original_filename = :orig, mime_type = :mime, file_size = :size
                     WHERE id = :id'
                );
                $stmt->execute([
                    'path' => $path,
                    'orig' => (string) $fileName['name'],
                    'mime' => (string) ($fileCheck['mime'] ?? 'application/octet-stream'),
                    'size' => (int) ($fileCheck['size'] ?? 0),
                    'id' => (int) $version['id'],
                ]);
            }
        } catch (\Throwable $e) {
            flash('error', 'Manuscript: the file could not be saved. Try a PDF or DOCX under ' . FileUpload::maxMb() . ' MB.');
            redirect('reader', ['id' => $id]);
        }
        Audit::log('study.replace_file', 'study', $id);
        flash('success', 'Manuscript file saved. Open the study to read it.');
        redirect('reader', ['id' => $id]);
    }

    public function reviseForm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || (int) $study['submitter_user_id'] !== (int) $user['id']) {
            redirect('studies');
        }
        if ($study['status'] !== 'revision_requested') {
            flash('error', 'Revision is only allowed when revision is requested.');
            redirect('studies/view', ['id' => $id]);
        }
        view('studies/revise', ['study' => $study, 'error' => flash('error')]);
    }

    public function reviseSubmit(): void
    {
        if (request_exceeded_post_max()) {
            flash('error', FileUpload::sizeError());
            redirect('studies/revise', ['id' => (int) ($_POST['id'] ?? 0)]);
        }
        Csrf::requireValid();
        $id = (int) ($_POST['id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || (int) $study['submitter_user_id'] !== (int) $user['id'] || $study['status'] !== 'revision_requested') {
            redirect('studies');
        }
        $fileCheck = $this->incomingManuscript(true);
        if (!$fileCheck['ok']) {
            remember_form(['change_note' => (string) ($_POST['change_note'] ?? '')]);
            flash('error', (string) $fileCheck['error']);
            redirect('studies/revise', ['id' => $id]);
        }
        $versions = Study::versions($id);
        $next = ($versions[0]['version_number'] ?? 0) + 1;
        try {
            $path = FileUpload::storeIncoming($id, $next, $fileCheck, $_FILES['manuscript'] ?? [], (int) $user['id'], '_full');
        } catch (\Throwable $e) {
            flash('error', 'Manuscript: the file could not be saved. Try a PDF or DOCX under ' . FileUpload::maxMb() . ' MB.');
            redirect('studies/revise', ['id' => $id]);
        }
        $note = trim($_POST['change_note'] ?? '');
        Study::addVersion(
            $id,
            (int) $user['id'],
            ['name' => (string) ($fileCheck['name'] ?? ($_FILES['manuscript']['name'] ?? 'manuscript.pdf'))],
            $path,
            (string) ($fileCheck['mime'] ?? 'application/octet-stream'),
            (int) ($fileCheck['size'] ?? ($_FILES['manuscript']['size'] ?? 0)),
            $note
        );
        if (($user['role_slug'] ?? '') !== 'student' && ($study['pass_to'] ?? '') === 'library') {
            Study::passToLibrary($id);
            foreach (User::all('dept_research_coordinator') as $librarian) {
                Notification::create((int) $librarian['id'], 'study', 'Revised study ready for department review', $study['title'], 'library/review?id=' . $id);
            }
        } else {
            Study::resubmit($id);
            if (!empty($study['adviser_user_id'])) {
                Notification::create(
                    (int) $study['adviser_user_id'],
                    'study',
                    'Revised study ready for adviser review',
                    $study['title'],
                    'review/ethics?id=' . $id
                );
            }
        }
        Audit::log('study.resubmit', 'study', $id);
        redirect('studies/view', ['id' => $id]);
    }

    public function submitStudy(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || (int) $study['submitter_user_id'] !== (int) $user['id'] || $study['status'] !== 'draft') {
            redirect('studies');
        }
        if (!$study['current_version_id']) {
            flash('error', 'Upload a manuscript before submitting.');
            redirect('studies/view', ['id' => $id]);
        }
        $this->deliverStudy($id, (string) ($study['pass_to'] ?? 'teacher'), (int) ($study['adviser_user_id'] ?? 0) ?: null, $study['title']);
        redirect('studies/view', ['id' => $id]);
    }

    public function submitToLibrarian(): void
    {
        Csrf::requireValid();
        $id = (int) ($_POST['id'] ?? 0);
        $study = Study::find($id);
        $user = Auth::user();
        if (!$study || (int) $study['submitter_user_id'] !== (int) $user['id'] || $study['status'] !== 'adviser_reviewed') {
            flash('error', 'Your research adviser must approve this study before you can send it to the dept research coordinator.');
            redirect('track', ['tab' => 'waiting']);
        }
        if (!Study::sendToLibrarian($id)) {
            flash('error', 'This study could not be sent to the dept research coordinator.');
            redirect('studies/view', ['id' => $id]);
        }
        foreach (User::all('dept_research_coordinator') as $librarian) {
            Notification::create((int) $librarian['id'], 'study', 'Study ready for department review', $study['title'], 'library/review?id=' . $id);
        }
        Audit::log('study.submit_library', 'study', $id);
        flash('success', 'Sent to the dept research coordinator.');
        redirect('studies/view', ['id' => $id]);
    }

    /** @return list<array{first_name:string,middle_name:string,last_name:string}> */
    private function parseAuthors(array $post): array
    {
        $first = $post['author_first'] ?? [];
        $middle = $post['author_middle'] ?? [];
        $last = $post['author_last'] ?? [];
        $authors = [];
        if (!is_array($first)) {
            return [];
        }
        foreach ($first as $i => $fn) {
            $authors[] = [
                'first_name' => trim((string) $fn),
                'middle_name' => trim((string) ($middle[$i] ?? '')),
                'last_name' => trim((string) ($last[$i] ?? '')),
            ];
        }
        return array_values(array_filter($authors, static fn ($a) => $a['first_name'] !== '' || $a['last_name'] !== ''));
    }

    /** @return array<string, mixed> */
    private function editableStudy(int $id): array
    {
        $study = Study::find($id);
        $user = Auth::user();
        $editable = ['draft', 'revision_requested'];
        if (!$study || (int) $study['submitter_user_id'] !== (int) $user['id'] || !in_array($study['status'], $editable, true)) {
            flash('error', 'You can edit or delete a study only while it is still a draft.');
            redirect('profile');
        }
        return $study;
    }

    private function deliverStudy(int $studyId, string $passTo, ?int $adviserId, string $title): void
    {
        $uploader = Auth::user();
        if (($uploader['role_slug'] ?? '') === 'student') {
            $passTo = 'teacher';
        }
        if ($passTo === 'library') {
            if (!Study::passToLibrary($studyId)) {
                flash('error', 'This study could not be sent to the dept research coordinator.');
                return;
            }
            foreach (User::all('dept_research_coordinator') as $librarian) {
                Notification::create((int) $librarian['id'], 'study', 'Study ready for department review', $title, 'library/review?id=' . $studyId);
            }
            Audit::log('study.submit_library', 'study', $studyId);
            flash('success', ($uploader['role_slug'] ?? '') === 'research_adviser'
                ? 'Submitted directly to the dept research coordinator.'
                : 'Passed to the dept research coordinator.');
            return;
        }
        if (!$adviserId) {
            flash('error', 'Choose a research adviser. The adviser accepts the study before it goes to the dept research coordinator.');
            return;
        }
        Study::submit($studyId);
        Notification::create($adviserId, 'study', 'Study ready for adviser review', $title, 'review/ethics?id=' . $studyId);
        Audit::log('study.submit', 'study', $studyId);
        flash('success', 'Passed to your research adviser. After the adviser approves it, you send it to the dept research coordinator.');
    }

    private function adviserDepartmentError(int $programId, int $adviserId): string
    {
        if ($programId < 1 || $adviserId < 1) {
            return '';
        }
        $adviser = User::findById($adviserId);
        if (!$adviser || ($adviser['role_slug'] ?? '') !== 'research_adviser' || (int) ($adviser['program_id'] ?? 0) !== $programId) {
            return 'Choose a research adviser from the same department. That adviser checks the study before the dept research coordinator.';
        }
        return '';
    }

    /** @param array<string, mixed> $extra */
    private function uploadFormData(array $extra = []): array
    {
        $user = Auth::user();
        $programId = (int) ($user['program_id'] ?? 0);
        $advisers = $programId > 0
            ? User::inProgram($programId, ['research_adviser'])
            : User::all('research_adviser');
        return array_merge([
            'programs' => Program::directorAdded(),
            'advisers' => $advisers,
            'schoolYears' => Study::schoolYearOptions(),
            'lockedProgramId' => $programId,
        ], $extra);
    }

    private function forceUploaderDepartment(): void
    {
        $user = Auth::user();
        $programId = (int) ($user['program_id'] ?? 0);
        if ($programId > 0 && in_array($user['role_slug'] ?? '', ['student', 'research_adviser'], true)) {
            $_POST['program_id'] = (string) $programId;
        }
    }

    private function forceAdviserLibraryPass(): void
    {
        $user = Auth::user();
        if (($user['role_slug'] ?? '') === 'research_adviser') {
            $_POST['pass_to'] = 'library';
        }
    }

    private function forceStudentAdviserPass(): void
    {
        $user = Auth::user();
        if (($user['role_slug'] ?? '') === 'student') {
            $_POST['pass_to'] = 'teacher';
        }
    }

    /** @return array{ok:bool,skip?:bool,error?:string,mime?:string,ext?:string,name?:string,size?:int,staged?:bool,token?:string} */
    private function incomingManuscript(bool $required): array
    {
        $user = Auth::user();
        $token = trim((string) ($_POST['manuscript_stored'] ?? ''));
        if ($token !== '') {
            return FileUpload::fromStaged($token, (int) ($user['id'] ?? 0));
        }
        if (!$required && empty($_FILES['manuscript']['name'])) {
            return ['ok' => true, 'skip' => true];
        }
        return FileUpload::validateManuscript($_FILES['manuscript'] ?? []);
    }

    /** @param array<string, mixed> $study */
    private function currentFileMissing(array $study): bool
    {
        $versions = Study::versions((int) ($study['id'] ?? 0));
        $currentId = (int) ($study['current_version_id'] ?? 0);
        foreach ($versions as $row) {
            if ((int) $row['id'] === $currentId) {
                $path = (string) ($row['stored_path'] ?? '');
                return $path === '' || !StorageFiles::exists($path);
            }
        }
        return $versions === [];
    }
}
