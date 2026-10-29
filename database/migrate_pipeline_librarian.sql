-- Pipeline: student → research_adviser → dept_research_coordinator → dean
-- → school_research_coordinator → librarian → school_administrator

ALTER TABLE studies
  MODIFY status ENUM(
    'draft','submitted','adviser_reviewed','pending','dean_review','ethics_review','under_review',
    'library_review','admin_review','revision_requested','resubmitted','approved','archived'
  ) NOT NULL DEFAULT 'draft';

INSERT INTO roles (id, slug, name, description) VALUES
(7, 'librarian', 'Librarian', 'Library gate after school research coordinator; before school administrator')
ON DUPLICATE KEY UPDATE
  slug = VALUES(slug),
  name = VALUES(name),
  description = VALUES(description);

INSERT INTO users (school_id, password_hash, first_name, middle_name, last_name, role_id, is_active, must_change_password, created_by)
SELECT 'LIB-0002', 'library2026', 'Rosa', 'L.', 'Librarian', 7, 1, 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE school_id = 'LIB-0002');

-- Studies already approved stay approved. In-flight under_review stays for SRC.
