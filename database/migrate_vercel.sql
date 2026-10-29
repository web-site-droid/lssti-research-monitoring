-- Tables used when the app runs on Vercel (sessions + uploaded files).
-- Safe to run on Hostinger or XAMPP as well.

CREATE TABLE IF NOT EXISTS stored_files (
  stored_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(128) NOT NULL,
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  content LONGBLOB NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (stored_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS php_sessions (
  id VARCHAR(128) NOT NULL,
  data MEDIUMBLOB NOT NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_php_sessions_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
