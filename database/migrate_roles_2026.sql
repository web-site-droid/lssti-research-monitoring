-- Role hierarchy: student < research_adviser < dept_research_coordinator
-- < dean < school_research_coordinator < school_administrator

SET FOREIGN_KEY_CHECKS = 0;

UPDATE roles SET slug = 'school_administrator', name = 'School Administrator',
    description = 'System administrator; full access and final oversight'
WHERE id = 1;

UPDATE roles SET slug = 'research_adviser', name = 'Research Adviser',
    description = 'Reviews assigned student manuscripts'
WHERE id = 2;

UPDATE roles SET slug = 'student', name = 'Student',
    description = 'Submits and revises research studies'
WHERE id = 3;

UPDATE roles SET slug = 'dept_research_coordinator', name = 'Dept Research Coordinator',
    description = 'Department research gate after the adviser'
WHERE id = 5;

INSERT INTO roles (id, slug, name, description) VALUES
(4, 'dean', 'Dean', 'College/school dean approval gate'),
(6, 'school_research_coordinator', 'School Research Coordinator', 'Ethics, plagiarism, and academic approval')
ON DUPLICATE KEY UPDATE
  slug = VALUES(slug),
  name = VALUES(name),
  description = VALUES(description);

SET FOREIGN_KEY_CHECKS = 1;

-- Extend study workflow with dean gate
ALTER TABLE studies
  MODIFY status ENUM(
    'draft','submitted','adviser_reviewed','pending','dean_review','ethics_review','under_review',
    'revision_requested','resubmitted','approved','archived'
  ) NOT NULL DEFAULT 'draft';

-- Demo accounts for new roles (password matches plain-text auth style used in this DB)
INSERT INTO users (school_id, password_hash, first_name, middle_name, last_name, role_id, is_active, must_change_password, created_by)
SELECT 'DEAN-0001', 'dean2026', 'Elena', 'R.', 'Dean', 4, 1, 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE school_id = 'DEAN-0001');

INSERT INTO users (school_id, password_hash, first_name, middle_name, last_name, role_id, is_active, must_change_password, created_by)
SELECT 'SRC-0001', 'coordinator', 'Carlos', 'M.', 'Coordinator', 6, 1, 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE school_id = 'SRC-0001');
