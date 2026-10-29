# Supabase integration (not used)

**LSSTI uses PHP + XAMPP + MySQL on the LAN only.** This document is kept for reference; do not enable cloud sync for the capstone deployment.

## Model (reference)

| Layer | Role |
|--------|------|
| **MySQL (XAMPP)** | Primary database — works **offline** on the school LAN |
| **Supabase (Postgres + Storage)** | Optional **cloud mirror** when the server has internet |

Login, sessions, and day-to-day use still run on PHP + MySQL. Supabase does **not** replace local auth.

## Cloud tables (project `knrrurercehjkkizuzoq`)

- `research_audit_logs` — copy of admin/download actions  
- `research_read_logs` — who opened which study  
- `research_study_index` — metadata for approved studies  
- Storage bucket `lssti-manuscripts` — optional file mirror  

RLS is **enabled** on `research_*` tables. Audit and read logs stay **service-role only**. `research_study_index` has an optional **anon read** policy for `status = 'approved'` (see `database/supabase/migrations/002_research_study_index_public_read.sql`) so browser JS and Vercel can show a public catalog without the service role key.

## Setup

1. Copy `config/supabase.example.php` → `config/supabase.local.php`  
2. In [Supabase Dashboard → Settings → API](https://supabase.com/dashboard/project/knrrurercehjkkizuzoq/settings/api), copy:
   - **Project URL** → `SUPABASE_URL`  
   - **service_role** secret → `SUPABASE_SERVICE_ROLE_KEY` (server only)  
   - **anon public** → `SUPABASE_ANON_KEY` (browser / Vercel only; optional)  
3. Log in as **Research Director** → **Settings** → enable **Supabase sync** and choose what to sync.  
4. For JavaScript, Vercel, and GitHub: see [DEPLOY_VERCEL_GITHUB.md](DEPLOY_VERCEL_GITHUB.md).  
4. Approve a study or open the reader once; check rows in Supabase **Table Editor** for `research_*` tables.

## When internet is down

Sync calls fail silently (short timeout). Local MySQL continues to work. Events are stored locally; you can add a future “replay queue” if you need backfill.

## Security

- Do **not** commit `supabase.local.php`.  
- Do **not** embed the service role key in JavaScript or mobile clients.  
- Your Supabase project also contains **other** apps (OJT tables). LSSTI uses separate `research_*` names—do not drop unrelated tables.

## Migrations

Applied via Supabase MCP:

- `lssti_research_sync_tables`  
- `lssti_research_storage_bucket`  

SQL reference: `database/supabase/migrations/` (mirrors applied migrations).
