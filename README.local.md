# Local run instructions

1. Start XAMPP Apache + MySQL.
2. Make sure the database exists:
   - DB name: `u471965942_researchdb`
   - default local user: `root`
   - password: empty
3. Copy the example config if needed:
   - `copy config\database.local.example.php config\database.local.php`
   - or create `config/database.local.php` with the same values.
4. Run the project locally:
   - `cd c:\xampp\htdocs\research`
   - `php -S 127.0.0.1:8000 -t .`
5. Open:
   - `http://127.0.0.1:8000/index.php?route=login`

Notes:
- Do not commit `config/database.local.php`.
- Do not commit `.env` or live Vercel secret files.
- For GitHub/Vercel handoff, commit the app code and example config files only.

## Configuration and verification

PHP does not load `.env` files automatically. Use `config/database.local.php` for
local development; configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and
`DB_PASS` in the hosting environment for deployment. The hardcoded production
password fallback has been removed. Set the deployment password before deploying
and rotate the previously exposed password through the database host.

Run the isolated regression suite (no database or network access):

```powershell
C:\xampp\php\php.exe tests\regression.php
```

Before committing, review tracked changes and deletions in VS Code. Include these
new application files along with the tracked changes that reference them:

- `app/models/DownloadLog.php`
- `app/views/partials/guide_modal.php`
- `app/views/partials/nav_sheet.php`
- `app/views/partials/readers_table.php`
- `app/views/partials/replace_file.php`
- `public/assets/js/manuscript-upload.js`

Also include `tests/regression.php`, `.env.example`, this README, and the handoff
documentation. Review `.cursor/` separately; exclude local secrets and server logs.
Do not use a blanket staging command without reviewing the existing deletions.
