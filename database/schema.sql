-- LSSTI Digital Research Repository
-- Import this file in phpMyAdmin after you click your database on the left.
-- Hostinger database: u471965942_researchdb
-- XAMPP database: lssti_research
-- This file does not create a database and does not switch databases.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS search_logs;
DROP TABLE IF EXISTS read_logs;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS plagiarism_results;
DROP TABLE IF EXISTS ethics_checklists;
DROP TABLE IF EXISTS review_scores;
DROP TABLE IF EXISTS review_comments;
DROP TABLE IF EXISTS review_assignments;
DROP TABLE IF EXISTS study_bookmarks;
DROP TABLE IF EXISTS study_ratings;
DROP TABLE IF EXISTS study_versions;
DROP TABLE IF EXISTS study_keywords;
DROP TABLE IF EXISTS study_authors;
DROP TABLE IF EXISTS studies;
DROP TABLE IF EXISTS programs;
DROP TABLE IF EXISTS login_logs;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

CREATE TABLE roles (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(32) NOT NULL,
  name VARCHAR(64) NOT NULL,
  description VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id VARCHAR(32) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(64) NOT NULL,
  middle_name VARCHAR(64) NULL,
  last_name VARCHAR(64) NOT NULL,
  contact_number VARCHAR(32) NULL,
  email VARCHAR(255) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(80) NULL,
  state VARCHAR(80) NULL,
  photo_path VARCHAR(255) NULL,
  role_id TINYINT UNSIGNED NOT NULL,
  program_id SMALLINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_school_id (school_id),
  KEY idx_users_role (role_id),
  KEY idx_users_program (program_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id),
  CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  school_id_attempt VARCHAR(32) NULL,
  event ENUM('login_success','login_failed','logout') NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(512) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_ip_time (ip_address, created_at),
  KEY idx_login_user_time (user_id, created_at),
  CONSTRAINT fk_login_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  setting_key VARCHAR(64) NOT NULL,
  setting_value TEXT NOT NULL,
  updated_at DATETIME NULL,
  updated_by INT UNSIGNED NULL,
  PRIMARY KEY (setting_key),
  CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE programs (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(16) NOT NULL,
  name VARCHAR(128) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  director_added TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_programs_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
  ADD CONSTRAINT fk_users_program FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE SET NULL;

CREATE TABLE studies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  submitter_user_id INT UNSIGNED NOT NULL,
  title VARCHAR(512) NOT NULL,
  short_title VARCHAR(80) NOT NULL,
  abstract TEXT NOT NULL,
  research_objectives TEXT NOT NULL,
  study_type ENUM('proposal','final_defense','thesis','dissertation','capstone','research_article','research_paper') NOT NULL,
  program_id SMALLINT UNSIGNED NOT NULL,
  adviser_user_id INT UNSIGNED NULL,
  pass_to ENUM('teacher','library') NOT NULL DEFAULT 'teacher',
  school_year VARCHAR(9) NOT NULL,
  defense_date DATE NULL,
  coi_statement TEXT NOT NULL,
  status ENUM(
    'draft','submitted','adviser_reviewed','pending','dean_review','ethics_review','under_review',
    'library_review','admin_review','revision_requested','resubmitted','approved','archived'
  ) NOT NULL DEFAULT 'draft',
  double_blind TINYINT(1) NOT NULL DEFAULT 0,
  current_version_id INT UNSIGNED NULL,
  submitted_at DATETIME NULL,
  approved_at DATETIME NULL,
  library_accepted_at DATETIME NULL,
  library_retracted_at DATETIME NULL,
  congress_status ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_studies_status (status),
  KEY idx_studies_program (program_id),
  KEY idx_studies_submitter (submitter_user_id),
  KEY idx_studies_adviser (adviser_user_id),
  FULLTEXT KEY ft_studies_search (title, short_title, abstract, research_objectives),
  CONSTRAINT fk_studies_submitter FOREIGN KEY (submitter_user_id) REFERENCES users (id),
  CONSTRAINT fk_studies_program FOREIGN KEY (program_id) REFERENCES programs (id),
  CONSTRAINT fk_studies_adviser FOREIGN KEY (adviser_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE study_authors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  study_id INT UNSIGNED NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL,
  first_name VARCHAR(64) NOT NULL,
  middle_name VARCHAR(64) NULL,
  last_name VARCHAR(64) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_study_author_order (study_id, sort_order),
  KEY idx_study_authors_names (last_name, first_name),
  CONSTRAINT fk_study_authors_study FOREIGN KEY (study_id) REFERENCES studies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE study_keywords (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  study_id INT UNSIGNED NOT NULL,
  keyword VARCHAR(64) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_study_keyword (study_id, keyword),
  KEY idx_keyword (keyword),
  CONSTRAINT fk_study_keywords_study FOREIGN KEY (study_id) REFERENCES studies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE study_ratings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  study_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  stars TINYINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_study_rating (study_id, user_id),
  CONSTRAINT fk_study_ratings_study FOREIGN KEY (study_id) REFERENCES studies (id) ON DELETE CASCADE,
  CONSTRAINT fk_study_ratings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE study_bookmarks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  study_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_study_bookmark (study_id, user_id),
  CONSTRAINT fk_study_bookmarks_study FOREIGN KEY (study_id) REFERENCES studies (id) ON DELETE CASCADE,
  CONSTRAINT fk_study_bookmarks_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE study_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  study_id INT UNSIGNED NOT NULL,
  version_number SMALLINT UNSIGNED NOT NULL,
  stored_path VARCHAR(512) NOT NULL,
  blind_stored_path VARCHAR(512) NULL,
  blind_original_filename VARCHAR(255) NULL,
  blind_mime_type VARCHAR(128) NULL,
  blind_file_size INT UNSIGNED NULL,
  original_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(128) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  change_note VARCHAR(500) NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_study_version (study_id, version_number),
  CONSTRAINT fk_study_versions_study FOREIGN KEY (study_id) REFERENCES studies (id) ON DELETE CASCADE,
  CONSTRAINT fk_study_versions_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE studies
  ADD CONSTRAINT fk_studies_current_version FOREIGN KEY (current_version_id) REFERENCES study_versions (id) ON DELETE SET NULL;

CREATE TABLE review_assignments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  study_id INT UNSIGNED NOT NULL,
  reviewer_user_id INT UNSIGNED NOT NULL,
  assignment_type ENUM('adviser','panelist') NOT NULL,
  assigned_by INT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_review_assignment (study_id, reviewer_user_id, assignment_type),
  CONSTRAINT fk_review_assign_study FOREIGN KEY (study_id) REFERENCES studies (id) ON DELETE CASCADE,
  CONSTRAINT fk_review_assign_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users (id),
  CONSTRAINT fk_review_assign_by FOREIGN KEY (assigned_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE review_comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  study_id INT UNSIGNED NOT NULL,
  study_version_id INT UNSIGNED NULL,
  reviewer_user_id INT UNSIGNED NOT NULL,
  section_label VARCHAR(128) NOT NULL,
  comment_text TEXT NOT NULL,
  visibility ENUM('public','private') NOT NULL DEFAULT 'public',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_review_comments_study (study_id),
  CONSTRAINT fk_review_comments_study FOREIGN KEY (study_id) REFERENCES studies (id) ON DELETE CASCADE,
  CONSTRAINT fk_review_comments_version FOREIGN KEY (study_version_id) REFERENCES study_versions (id) ON DELETE SET NULL,
  CONSTRAINT fk_review_comments_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE review_scores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  study_id INT UNSIGNED NOT NULL,
  study_version_id INT UNSIGNED NULL,
  reviewer_user_id INT UNSIGNED NOT NULL,
  score DECIMAL(5,2) NOT NULL,
  remarks VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_review_scores_study (study_id),
  CONSTRAINT fk_review_scores_study FOREIGN KEY (study_id) REFERENCES studies (id) ON DELETE CASCADE,
  CONSTRAINT fk_review_scores_version FOREIGN KEY (study_version_id) REFERENCES study_versions (id) ON DELETE SET NULL,
  CONSTRAINT fk_review_scores_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ethics_checklists (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  study_id INT UNSIGNED NOT NULL,
  human_subjects ENUM('passed','failed','pending') NOT NULL DEFAULT 'pending',
  human_subjects_remarks TEXT NULL,
  informed_consent ENUM('passed','failed','pending') NOT NULL DEFAULT 'pending',
  informed_consent_remarks TEXT NULL,
  ethical_clearance ENUM('passed','failed','pending') NOT NULL DEFAULT 'pending',
  irb_code VARCHAR(64) NULL,
  irb_document_path VARCHAR(512) NULL,
  ethical_clearance_remarks TEXT NULL,
  data_privacy_ra10173 ENUM('passed','failed','pending') NOT NULL DEFAULT 'pending',
  data_privacy_remarks TEXT NULL,
  coi_disclosed ENUM('passed','failed','pending') NOT NULL DEFAULT 'pending',
  coi_remarks TEXT NULL,
  manuscript_format ENUM('passed','failed','pending') NOT NULL DEFAULT 'pending',
  manuscript_format_remarks TEXT NULL,
  last_updated_by INT UNSIGNED NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ethics_study (study_id),
  CONSTRAINT fk_ethics_study FOREIGN KEY (study_id) REFERENCES studies (id) ON DELETE CASCADE,
  CONSTRAINT fk_ethics_updated_by FOREIGN KEY (last_updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plagiarism_results (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  study_id INT UNSIGNED NOT NULL,
  study_version_id INT UNSIGNED NOT NULL,
  similarity_percentage DECIMAL(5,2) NOT NULL,
  screenshot_path VARCHAR(512) NULL,
  recorded_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_plagiarism_study (study_id, study_version_id),
  CONSTRAINT fk_plagiarism_study FOREIGN KEY (study_id) REFERENCES studies (id) ON DELETE CASCADE,
  CONSTRAINT fk_plagiarism_version FOREIGN KEY (study_version_id) REFERENCES study_versions (id) ON DELETE CASCADE,
  CONSTRAINT fk_plagiarism_recorded_by FOREIGN KEY (recorded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(32) NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NULL,
  link_route VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user (user_id, is_read, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE read_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  study_id INT UNSIGNED NOT NULL,
  study_version_id INT UNSIGNED NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_read_study_time (study_id, opened_at),
  KEY idx_read_user_time (user_id, opened_at),
  CONSTRAINT fk_read_logs_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_read_logs_study FOREIGN KEY (study_id) REFERENCES studies (id) ON DELETE CASCADE,
  CONSTRAINT fk_read_logs_version FOREIGN KEY (study_version_id) REFERENCES study_versions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,
  entity_type VARCHAR(32) NULL,
  entity_id INT UNSIGNED NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_action_time (action, created_at),
  KEY idx_audit_user_time (user_id, created_at),
  CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE search_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  query_text VARCHAR(255) NOT NULL,
  filters_json JSON NULL,
  result_count INT UNSIGNED NULL,
  searched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_search_time (searched_at),
  KEY idx_search_query (query_text(50)),
  CONSTRAINT fk_search_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Default accounts. Password for every account: ChangeMe@LSSTI2026

INSERT INTO roles (id, slug, name, description) VALUES
(1, 'school_administrator', 'School Administrator', 'Final publish and system settings'),
(2, 'research_adviser', 'Research Adviser', 'Reviews assigned student manuscripts'),
(3, 'student', 'Student', 'Submits and revises research studies'),
(4, 'dean', 'Dean', 'College/school dean approval gate'),
(5, 'dept_research_coordinator', 'Dept Research Coordinator', 'Department research gate after the adviser'),
(6, 'school_research_coordinator', 'School Research Coordinator', 'Ethics, plagiarism, then send to librarian'),
(7, 'librarian', 'Librarian', 'Library gate before school administrator');

INSERT INTO users (school_id, password_hash, first_name, middle_name, last_name, role_id, is_active, must_change_password, created_by) VALUES
('RD-0001', 'ChangeMe@LSSTI2026', 'Maria', 'L.', 'Administrator', 1, 1, 1, NULL),
('ADV-0001', 'ChangeMe@LSSTI2026', 'Juan', 'D.', 'Adviser', 2, 1, 1, 1),
('STU-0001', 'ChangeMe@LSSTI2026', 'Ana', 'S.', 'Researcher', 3, 1, 1, 1),
('DEAN-0001', 'ChangeMe@LSSTI2026', 'Elena', 'R.', 'Dean', 4, 1, 1, 1),
('LIB-0001', 'ChangeMe@LSSTI2026', 'Lina', 'M.', 'Coordinator', 5, 1, 1, 1),
('SRC-0001', 'ChangeMe@LSSTI2026', 'Carlos', 'M.', 'Coordinator', 6, 1, 1, 1),
('LIB-0002', 'ChangeMe@LSSTI2026', 'Rosa', 'L.', 'Librarian', 7, 1, 1, 1);

INSERT INTO settings (setting_key, setting_value) VALUES
('max_upload_mb', '25'),
('session_timeout_minutes', '30'),
('plagiarism_block_percent', '10'),
('ip_allowlist_enabled', '0'),
('ip_allowlist_cidrs', '["192.168.0.0/16","10.0.0.0/8"]'),
('login_max_attempts', '5'),
('login_lockout_minutes', '15');

INSERT INTO programs (code, name, is_active, director_added) VALUES
('BSCRIM', 'Bachelor of Science in Criminology', 1, 1),
('BSN', 'Bachelor of Science in Nursing', 1, 1),
('BSCS', 'Bachelor of Science in Computer Science', 1, 1),
('BSBA', 'Bachelor of Science in Business Administration', 1, 1),
('BSED', 'Bachelor of Secondary Education', 1, 1);

UPDATE users
SET program_id = (SELECT id FROM programs WHERE code = 'BSCS' LIMIT 1)
WHERE role_id IN (2, 3, 4, 5);
