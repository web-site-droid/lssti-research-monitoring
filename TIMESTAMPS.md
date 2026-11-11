All displayed times use Asia/Manila and are labeled PHT (UTC+8).

`studies.upload_date` is the selected calendar date. It is displayed separately
from `studies.created_at`, the automatically recorded upload time.

MySQL DATETIME fields have no timezone metadata. `DB_TIMEZONE` controls both
the connection's session timezone and the interpretation of stored timestamps.
Defaults preserve the existing conventions: +08:00 for local XAMPP and +00:00
for hosted requests, including Vercel. Set the DB_TIMEZONE environment variable
if a host uses another convention. Keep it consistent with existing rows;
changing it does not migrate historical data. No historical rows were rewritten.

PHP uses Asia/Manila for today's date, validation, and generated reports.
Session expiration and report date filters are converted to the storage timezone.
Track groups and month filters use the Philippine calendar day.

Validation: `php tests/philippine-timestamps.php`,
`php tests/local-timestamps.php`, and the existing PHP/JavaScript suites.
