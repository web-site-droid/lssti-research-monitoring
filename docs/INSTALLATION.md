# Installation (XAMPP + school LAN)

This deployment is **private to the school network** — not public on the internet. Use **PHP + MySQL on XAMPP** only. Supabase, Vercel, and the `web/` folder are **not** part of this setup.

## 1. Copy project
Place the folder under `C:\xampp\htdocs\lssti_research_repository`.

## 2. Apache document root (recommended)
Point Apache to `...\lssti_research_repository\public` via VirtualHost, **or** browse to:

`http://<server-ip>/lssti_research_repository/public/index.php?route=login`

Links now work on **any port** (80, 8080, etc.) when you open the app from that same URL.

### Using port 8080 (common XAMPP setup)

If Apache uses port **8080**, open:

`http://localhost:8080/lssti_research_repository/public/index.php?route=login`

Optional: in `config/config.php` set `define('APP_HTTP_PORT', 8080);` if generated links ever miss the port.

If your URL path differs, adjust `APP_URL_PATH` (CLI/fallback only).

## 3. Database
1. Start MySQL in XAMPP.
2. Import `database/schema.sql` (phpMyAdmin or CLI). Select the database first. Default accounts are included in that file.

## 4. First login
- **School ID:** `RD-0001`
- **Password:** `ChangeMe@LSSTI2026` (change immediately)

## 5. LAN access
1. Set a static IPv4 on the server PC (e.g. `192.168.1.50`).
2. Allow Apache (port 80) in Windows Firewall on private networks.
3. Clients use `http://192.168.1.50/lssti_research_repository/public/`.
4. Optional: enable IP allowlist under **Settings** (Research Director).

## 6. Offline libraries
Mammoth and PDF.js are bundled under `public/assets/vendor/`. No CDN required.

## 7. Backups
From project root:

```bat
set MYSQLDUMP_PATH=C:\xampp\mysql\bin\mysqldump.exe
php scripts\backup.php
```

Outputs go to `backups/`.
