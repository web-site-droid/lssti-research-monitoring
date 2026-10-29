<?php

declare(strict_types=1);

/**
 * Copy to supabase.local.php and fill in values from Supabase Dashboard → Settings → API.
 * NEVER commit supabase.local.php or put the service_role key in the browser.
 *
 * Project URL example: https://YOUR_PROJECT_REF.supabase.co
 */

define('SUPABASE_URL', 'https://knrrurercehjkkizuzoq.supabase.co');
define('SUPABASE_SERVICE_ROLE_KEY', 'your-service-role-key-here');
/** Publishable anon key — safe in browser with RLS on research_study_index (approved read only). */
define('SUPABASE_ANON_KEY', 'your-anon-key-here');
define('SUPABASE_STORAGE_BUCKET', 'lssti-manuscripts');
