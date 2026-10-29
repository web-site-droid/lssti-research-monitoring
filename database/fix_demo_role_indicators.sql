-- Canonical role names + demo School IDs mapped to the correct role.

UPDATE roles SET name = 'School Administrator', description = 'Final publish and system settings' WHERE slug = 'school_administrator';
UPDATE roles SET name = 'Research Adviser', description = 'Reviews assigned student manuscripts' WHERE slug = 'research_adviser';
UPDATE roles SET name = 'Student', description = 'Submits and revises research studies' WHERE slug = 'student';
UPDATE roles SET name = 'Dean', description = 'College/school dean approval gate' WHERE slug = 'dean';
UPDATE roles SET name = 'Dept Research Coordinator', description = 'Department research gate after the adviser' WHERE slug = 'dept_research_coordinator';
UPDATE roles SET name = 'School Research Coordinator', description = 'Ethics, plagiarism, then send to librarian' WHERE slug = 'school_research_coordinator';
UPDATE roles SET name = 'Librarian', description = 'Library gate before school administrator' WHERE slug = 'librarian';

UPDATE users u
INNER JOIN roles r ON r.slug = 'student'
SET u.role_id = r.id
WHERE u.school_id = 'STU-0001';

UPDATE users u
INNER JOIN roles r ON r.slug = 'research_adviser'
SET u.role_id = r.id
WHERE u.school_id = 'ADV-0001';

UPDATE users u
INNER JOIN roles r ON r.slug = 'dept_research_coordinator'
SET u.role_id = r.id
WHERE u.school_id = 'LIB-0001';

UPDATE users u
INNER JOIN roles r ON r.slug = 'dean'
SET u.role_id = r.id
WHERE u.school_id = 'DEAN-0001';

UPDATE users u
INNER JOIN roles r ON r.slug = 'school_research_coordinator'
SET u.role_id = r.id
WHERE u.school_id = 'SRC-0001';

UPDATE users u
INNER JOIN roles r ON r.slug = 'librarian'
SET u.role_id = r.id
WHERE u.school_id = 'LIB-0002';

UPDATE users u
INNER JOIN roles r ON r.slug = 'school_administrator'
SET u.role_id = r.id
WHERE u.school_id = 'RD-0001';
