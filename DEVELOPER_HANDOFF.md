# Developer handoff — 2026-11-05

This describes the current working tree, compared with commit `aa91937` on `main` (Deploy the LSSTI research app to Vercel). It is a static code review, not confirmation that the deployed site or database matches this folder.

## Follow-up fixes

The initial review below is preserved as a record of the inherited code. The following fixes have since been applied locally, without a commit or deployment:

- Administrator normalization and labels now preserve the administrator role. Permission helpers use the current database user role, so stale session labels do not retain incorrect privileges. Administrator folder access is enabled, and final-publisher permissions exclude librarians.
- Department-bound account creation validates the requested role and the actor's department without reading an undefined account ID.
- Login lockout uses failed login records and the existing configurable attempt/window limits. Blocked requests do not append another failure and extend their own lockout.
- Coordinator approval and final publication now require passed ethics and format checks plus a numeric similarity result for the current version below the configured blocking threshold. At the default setting, 10% or higher blocks advancement. The review screen provides forms to record these checks and disables advancement until they pass.
- The hardcoded database password fallback was removed. Hosting credentials must be supplied through environment configuration. Rotation of the previously exposed credential still requires the hosting account and has not been performed.
- Local instructions list all new application files required by the tracked changes, and the server log is ignored. Files have not been staged; include those new files in the eventual reviewed commit.

Verification: 96 isolated regression checks passed and 97 PHP files passed syntax checks. The suite uses an in-memory fixture database interface, not MySQL, so live SQL compatibility and the complete browser workflow still need an integration check. Run `C:\xampp\php\php.exe tests\regression.php` to repeat the suite.

## Source control snapshot

Before this handoff file was added: 67 modified tracked files, 26 deleted tracked files, and 10 untracked entries. No staged changes were found. The tracked diff contains 2,316 insertions and 1,915 deletions across 93 files; untracked file contents are not included in those totals.

- Modified means the local file differs from the last commit.
- Deleted means the file existed in Git but is missing locally. Committing the deletion removes it from the next version, while earlier commits retain it.
- Untracked means Git is not yet saving the file. It will not be included in a normal commit until added.
- Staging selects the changes to include in the next commit. A commit records a local snapshot; pushing shares commits with the remote. These local changes are not evidence that they have been deployed.

New application files include `app/models/DownloadLog.php`, `public/assets/js/manuscript-upload.js`, and partial views for guides, navigation, reader tables, and missing-file replacement. Changed tracked files reference these new files, so they must travel together. Also untracked: `.cursor/`, `.env.example`, `README.local.md`, and `php-server.log`. Review editor files and logs before staging.

## What this system is

The Lanao School of Science and Technology Digital Research Repository manages manuscript submission, academic review, publication, discovery, and usage reporting.

It uses custom PHP MVC code with PDO/MySQL, PHP templates, CSS, and browser JavaScript. There is no Composer or npm manifest in the project root. The reader uses PDF.js for PDFs and Mammoth for DOCX conversion. Supabase integration helpers remain as optional synchronization/storage facilities; MySQL is the application's primary data store.

## Request cycle and code map

1. Apache routes requests into `public/`; `public/index.php` loads `app/bootstrap.php`. The root `index.php` redirects to the login entry point. Vercel uses `api/index.php`.
2. Bootstrap loads configuration, database functions, the custom autoloader, and shared functions. It ensures storage/session tables exist, starts the session, and applies the optional IP allowlist.
3. `app/Router.php` reads the `route` query parameter and chooses a controller method. It checks login, mandatory password changes, and allowed roles. Its dispatch logic uses an explicit public-route list; route metadata alone does not determine public access.
4. Controllers validate input and CSRF tokens, apply business rules, call models, create notifications/audit entries, then render a view or redirect.
5. Models execute SQL through `db()` in `config/database.php`.
6. Views under `app/views/` render HTML. Scripts under `public/assets/js/` handle uploads, search, navigation, and reading.

Start reading `app/Router.php`, `app/helpers/Role.php`, `app/models/Study.php`, then `StudyController.php`, `LibraryController.php`, and `ReviewController.php`.

## Research process cycle

The intended updated sequence is:

Student → Research adviser → Department research coordinator → Dean → School research coordinator → School administrator → Published repository.

| Step | What happens | Database status |
| --- | --- | --- |
| Prepare | Student enters metadata, authors, keywords, department, adviser, and manuscript; saves a draft | `draft` |
| Submit | Student passes the paper to the adviser | `submitted` |
| Adviser review | Adviser reviews the assigned paper and finishes the adviser step | `adviser_reviewed` |
| Department handoff | Student forwards the adviser-reviewed paper to the department coordinator | `pending` |
| Department review | Coordinator saves a review note and forwards it to the dean | `dean_review` |
| Dean review | Dean forwards the paper to the school research coordinator | `under_review` |
| School research review | Coordinator records ethics/format/similarity information and forwards the paper | `admin_review` |
| Final publication | School administrator publishes it | `approved` |

`approved` is the published state; the school coordinator's Approve action forwards to the administrator rather than publishing.

Reviewers can request revision (`revision_requested`). The student uploads a new numbered version and change note. The normal student resubmission path returns to `submitted`, so revision does not automatically resume at the reviewer who requested it. Rejection uses `archived`. Older statuses and route/method names remain for compatibility: `library_review` is still accepted in some paths, and names such as `passToLibrary()` and `sendToLibrarian()` can actually mean department review. Read their SQL before changing them.

The code contains older adviser-upload/direct-department branches, but current create/edit routes restrict those operations to students. A helper or template branch does not necessarily mean that feature is reachable.

Ethics and plagiarism are recorded by staff. The shown code records a similarity percentage and optional screenshot; this is not an automatic plagiarism scanning service. Crucially, `Study::canApprove()` and `canFinalPublish()` currently check workflow status, not completion of every ethics field or a similarity threshold. Do not assume those checks are enforced merely because the forms and settings exist.

## Roles and access

- Student: submit and revise own research; full manuscripts for own uploads and own department under the manuscript access helper.
- Adviser: review assigned papers; full manuscript access for own department, assigned advisees, and own uploads.
- Department coordinator and dean: department review responsibilities and department manuscript access.
- School research coordinator: school-wide manuscripts and academic review.
- School administrator: intended final publisher and senior account/settings manager.
- Librarian: remains in permission groups despite removal of the dedicated approval gate. Several groups grant this role broad privileges.

Public search and reader routes exist, but full manuscript access requires login and passes separate access checks. Catalog visibility, study-management access, and manuscript access are distinct decisions in the code.

Account management includes account creation/editing, spreadsheet imports, password resets, and department management. Some roles are constrained to a department. The current role implementation has contradictions described below, so this is an intended responsibility map rather than proof that each role works correctly.

## Upload, reading, and reporting cycle

1. Manuscript forms can upload in 512 KiB pieces through `studies/upload-part`.
2. The server stages pieces by user/token, combines them, and validates size, extension, and file signature.
3. The form submits a staging token. The controller saves the manuscript and creates a `study_versions` record; `studies.current_version_id` identifies the active version.
4. Storage uses database blobs on Vercel. Regular server uploads can use disk plus a database copy; the staged-upload path writes through the database content helper as well. Back up both MySQL and `storage/`.
5. File lookup can fall back to another file in the same study folder when the exact path is missing. This can restore access, but may select a different version; verify document identity when diagnosing it.
6. The reader renders PDF/DOCX content and handles page navigation and speech features. Missing files now produce a recovery interface, with replacement subject to permissions.
7. Read events go to `read_logs`; downloads use `audit_logs` with action `study.download`. `DownloadLog` is a model over audit records, not a new download table.
8. Reports and per-study reader tables display latest activity per user/study. These summaries are not a count of every read or download. Some report queries cap results at 200 rows.

## Database map

| Area | Main tables |
| --- | --- |
| Accounts and departments | `users`, `roles`, `programs` |
| Manuscripts | `studies`, `study_versions`, `study_authors`, `study_keywords` |
| Review | `review_assignments`, `review_comments`, `review_scores` |
| Compliance records | `ethics_checklists`, `plagiarism_results` |
| Repository interactions | `study_bookmarks`, `study_ratings`, `read_logs`, `search_logs` |
| Administration | `notifications`, `login_logs`, `audit_logs`, `settings` |
| Runtime persistence | `stored_files`, `php_sessions`, created by storage initialization |

The tracked SQL dump `database/u471965942_researchdb.sql` remains. The separate schema, seed, migration scripts, Supabase migrations, and old documentation are locally deleted. A dump is not a substitute for a maintained migration history. Treat its contents as potentially sensitive when sharing the repository.

## What the current source-control changes do

| Changed area | Effect |
| --- | --- |
| Workflow, study model, review screens | Removes the librarian stage from the intended progress sequence; coordinator forwards directly to administrator; updates labels and notifications |
| Role/auth helpers and users | Adds legacy role normalization, changes privileges/account options, adds department creation/renaming, and caches the current user per request |
| Upload helper/controller/new upload script | Adds chunked staging, file-signature checks, oversized-request messages, and better save-failure handling |
| Storage helper and replacement partial | Adds disk/database lookup helpers and missing-file recovery/replacement |
| Search/file controllers, read/download models | Tracks current read/download activity and exposes authorized per-article reader/downloader tables |
| Reports controller/views | Adds reading/downloading summaries with department scoping for department reviewers |
| Reader JavaScript and template | Substantial changes to PDF/DOCX rendering, navigation, reader controls, and recovery presentation |
| CSS/layout/navigation/new partials | Refreshes interface layout, responsive navigation, guides, and supporting dialogs |
| Authentication/CSRF/session | Normalizes school IDs, changes login redirects, adds clearer expired-form handling, and adjusts session behavior |
| Rate limiting | Removes the login lockout call; `isLoginLocked()` now always returns false |
| Study controller/router/views | Removes Research Congress application/decision handlers and related interface/routes |
| Database configuration | Adds connection fallback/caching and deployment-specific error handling; configuration supports local and remote MySQL |
| Vercel/PHP configuration | Changes runtime configuration and selects `sin1` in `vercel.json` |
| Ignore rules | Excludes local environment/config/storage helper files and allows example environment files |
| Documentation/database deletions | Removes the previous README, documentation set, schema, and migrations; local-run README is newly untracked |

## Findings to address before relying on this version

1. **Administrator role contradiction:** `Role::canonical('school_administrator')` returns `librarian`. `User::normalize()` applies that normalization when reading users. `ReviewController::finalPublishSubmit()` requires the exact role `school_administrator`, so a normalized administrator is rejected. The same mismatch affects role-specific branches and administrator-only routes, including missing-file replacement. Decide the actual role model and make normalization, route guards, controller checks, and labels agree.
2. **Account creation error:** `UserController::createSubmit()` calls `User::findById($id)` before `$id` is defined in the department-bound branch. With strict types this can fail when a dean creates an account. The previous code validated the selected role; the changed code tries to validate an existing target during creation.
3. **Disabled login throttling:** both the controller lockout call and rate-limit implementation have been removed. Failed-login logging remains, but does not itself limit attempts.
4. **Credentials in configuration:** `config/config.php` contains a hardcoded nonlocal password fallback. Move deployment credentials to environment configuration and rotate any credential that has been shared or committed. This handoff deliberately does not reproduce the value.
5. **Compliance gates are not enforced by approval helpers:** the methods described above permit advancement based on status alone. Confirm the required business rules with the school before implementing enforcement.
6. **Incomplete commit risk:** new classes, partials, and JavaScript files are untracked while changed tracked files depend on them. Committing only modified tracked files produces an incomplete version.
7. **Recovery can substitute a file:** `StorageFiles::locate()` can choose a matching version prefix or the newest file in a study folder. A successful file lookup does not prove it found the originally requested manuscript version.
8. **Runtime schema initialization:** bootstrap can create tables during web requests, and caches completion using a shared temporary flag. That flag is not keyed by database, which can cause initialization to be skipped for another local database using the same temp directory.

`Role::repairStoredRoles()` also contains account-role reassignment and schema/status repair SQL, but no invocation was found in the current app. Its presence does not mean those repairs have executed.

## Local operation and verification

Start XAMPP Apache and MySQL. Review `README.local.md` and `config/database.local.example.php`; use a local-only `config/database.local.php`. The expected database name is `u471965942_researchdb`. For a fresh isolated database, review/import the tracked dump; do not overwrite an existing database without a backup.

With Apache, use the project under localhost. For a PHP development server rooted at the project, open `/public/index.php?route=login`; the root entry point redirects there too. The PHP development server does not enforce Apache `.htaccess` restrictions, so keep it local.

Deployment configuration and optional services must be checked against the actual hosting account separately. Existing local secrets and the live database were not accessed for this review.

Verification performed: PHP syntax checks passed for 96 PHP files under `app`, `config`, `api`, and `public`. No application source was changed. No database import, browser workflow test, deployment, commit, or push was performed. Syntax checks do not establish that permissions, uploads, or the approval cycle work.

Suggested next validation: use isolated test accounts for student, adviser, department coordinator, dean, school coordinator, and administrator; walk one paper through publication, then test revision, rejection, missing-file recovery, department isolation, and reports. Resolve the role and account-creation defects before treating that cycle as operational.
