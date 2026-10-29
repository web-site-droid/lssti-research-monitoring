<?php

declare(strict_types=1);

$local = CONFIG_PATH . '/supabase.local.php';
if (is_file($local)) {
    require_once $local;
}

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', '');
}
if (!defined('SUPABASE_SERVICE_ROLE_KEY')) {
    define('SUPABASE_SERVICE_ROLE_KEY', '');
}
if (!defined('SUPABASE_STORAGE_BUCKET')) {
    define('SUPABASE_STORAGE_BUCKET', 'lssti-manuscripts');
}
if (!defined('SUPABASE_ANON_KEY')) {
    define('SUPABASE_ANON_KEY', '');
}
