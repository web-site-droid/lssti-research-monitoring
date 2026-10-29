# Deploy this PHP app on Vercel

The repository now runs on Vercel with the PHP runtime. Keep XAMPP and Hostinger as they are. Vercel does not use `localhost` MySQL.

## 1. Remote MySQL

Vercel cannot reach `127.0.0.1`. Use the Hostinger database (or another MySQL host that allows remote connections).

In hPanel:

1. Open **Databases → Remote MySQL** (or **Databases → Management → Remote access**).
2. Allow access from `%` or from Vercel.
3. Copy the **remote host** (`srvNNNN.hstgr.io`). Do not use `localhost` and do not use `127.0.0.1` in Vercel.

Import `database/u471965942_researchdb.sql` in phpMyAdmin if this database is empty. The app also creates `php_sessions` and `stored_files` on first request.

## 2. GitHub

Push this project to a GitHub repository. Vercel imports from GitHub. After that, each push to the connected branch deploys again.

## 3. Vercel project

1. Open [vercel.com/new](https://vercel.com/new) and import the GitHub repo.
2. Framework preset: **Other**.
3. Root directory: leave empty (project root).
4. Add environment variables for **Production** and **Preview**:

| Name | Value |
|------|--------|
| `DB_HOST` | Hostinger remote host (`srvNNNN.hstgr.io`) |
| `DB_PORT` | `3306` |
| `DB_NAME` | `u471965942_researchdb` |
| `DB_USER` | `u471965942_root` |
| `DB_PASS` | the hPanel MySQL password |

5. Deploy.

The live URL looks like `https://your-project.vercel.app`. Sign in with the same school ID you use here.

## 4. Limits

- Uploads on Vercel are capped at **4 MB** (Vercel request size). XAMPP still allows 25 MB.
- New files uploaded on Vercel are stored in MySQL (`stored_files`). Files that exist only on the XAMPP disk are not copied automatically.
- Saving a file in Cursor does not update Vercel until you push to GitHub (or run `npx vercel --prod`).

## 5. Local check

XAMPP is unchanged:

`http://127.0.0.1/research/public/index.php?route=login`
