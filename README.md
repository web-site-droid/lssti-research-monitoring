# LSSTI Digital Research Repository

**New app (in progress):** [drr/README.md](drr/README.md) — Next.js + PostgreSQL. Phase 1 (schema) is ready; features start after confirmation.

**Current LAN app:** PHP + XAMPP + MySQL below. It still runs until you choose to retire it.

Web-based research management for **Lanao School of Science and Technology, Inc.**

**Stack:** PHP 8+ (plain MVC, PDO), **MySQL**, vanilla JS — runs on **XAMPP** on the school **LAN**. This system is **not public**; use login and optional IP allowlist.

## Quick start
1. Import `database/schema.sql` into MySQL (XAMPP or Hostinger). Select the database first. Do not import the old migration files.
2. Open the app in your browser, for example:
   - `http://localhost/lssti_research_repository/public/index.php?route=login`
   - Port **8080:** `http://localhost:8080/lssti_research_repository/public/index.php?route=login`
3. Login examples: **RD-0001** / `director` (school administrator), or **STU-0001** / `students`.

Full setup: [docs/INSTALLATION.md](docs/INSTALLATION.md).

## Structure
- `public/` — web root (`index.php`, assets)
- `app/` — controllers, models, views, helpers
- `config/` — `config.php`, `database.php`
- `storage/` — private uploads
- `database/` — schema, seed, migrations
- `docs/` — SDLC, testing, diagrams

## Roles / pipeline (low → high)
Student → Research Adviser → Dept Research Coordinator → Dean → School Research Coordinator → Librarian → School Administrator

## Accounts (imported DB)
| School ID | Role | Password |
|-----------|------|----------|
| `STU-0001` | Student | `students` |
| `ADV-0001` | Research Adviser | `advisers` |
| `LIB-0001` | Dept Research Coordinator | `librarian` |
| `DEAN-0001` | Dean | `dean2026` |
| `SRC-0001` | School Research Coordinator | `coordinator` |
| `LIB-0002` | Librarian | `library2026` |
| `RD-0001` | School Administrator | `director` |

System flow: [docs/SYSTEM_FLOW.md](docs/SYSTEM_FLOW.md). For a new database, import `database/schema.sql` once.

## Not used for this deployment
The folders `web/`, `integrations/vercel/`, and cloud sync docs are **optional experiments** — ignore them for XAMPP/LAN use. No Supabase or Vercel is required.
