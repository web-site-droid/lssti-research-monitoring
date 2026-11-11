<?php
declare(strict_types=1);

define('DB_TIMEZONE', '+08:00');
require dirname(__DIR__) . '/app/helpers/functions.php';

if (timestamp_label('2026-11-11 09:00:00') !== 'November 11, 2026 at 9:00 AM PHT') {
    throw new RuntimeException('Existing local UTC+8 timestamps must not shift by eight hours.');
}
if (philippine_datetime('2026-11-11 23:30:00')?->format('Y-m-d') !== '2026-11-11') {
    throw new RuntimeException('Local dates must not shift to tomorrow.');
}
if (upload_date_label(['created_at'=>'2026-11-11 23:30:00']) !== 'November 11, 2026') {
    throw new RuntimeException('Local legacy upload date fallback changed.');
}
echo "Passed 3 local timestamp compatibility checks.\n";
