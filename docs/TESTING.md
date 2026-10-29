# Manual testing checklist

## Module 1.0 — Users and access
- [ ] Login with valid credentials; invalid password fails
- [ ] Lockout after repeated failures from same IP
- [ ] Logout and login appear in login logs (director)
- [ ] CSRF: tampered token rejected on POST
- [ ] Director creates/edits/deactivates user; reset password
- [ ] Non-director cannot open `/users`
- [ ] Session expires after configured timeout

## Module 2.0 — Submit and revise
- [ ] Research objectives required and distinct from abstract; visible on reader/search
- [ ] Student submit: validation (abstract 350 words, 5–8 keywords, file type/size)
- [ ] Version increments (v1, v2) without overwriting files
- [ ] Draft vs submit status transitions
- [ ] Revision upload after `revision_requested`

## Module 3.0 — Review and ethics
- [ ] Ethics items saved; failed item blocks approval (server-side)
- [ ] Plagiarism > 10% blocks approval
- [ ] Comments and notifications created
- [ ] Double-blind hides author names from adviser review view
- [ ] Director approve / request revision / archive

## Module 4.0 — Search and read
- [ ] Auto-search updates while typing
- [ ] Suggest for title/author/keyword
- [ ] Filters and date range
- [ ] Reader: DOCX via mammoth, PDF via PDF.js
- [ ] TOC links scroll; TTS play/pause/stop
- [ ] Guests can open approved studies only
- [ ] Files only via `file/manuscript` controller

## Module 5.0 — Logs and reports
- [ ] Read log on open study (Reports → Read logs)
- [ ] Audit trail lists downloads and admin actions
- [ ] Download logged in audit trail
- [ ] CSV exports open in Excel
- [ ] Print report shows school header
