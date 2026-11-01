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
