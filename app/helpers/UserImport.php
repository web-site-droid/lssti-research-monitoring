<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Program;
use App\Models\User;
use RuntimeException;

final class UserImport
{
    public const DEFAULT_PASSWORD = 'changeme@lssti2026';
    public const MAX_ROWS = 500;

    /** @var list<string> */
    private const SAMPLE_IDS = ['SAMPLE-0001', 'SAMPLE-0002'];

    /**
     * @return list<array{name: string, rows: list<list<string>>}>
     */
    public static function templateSheets(): array
    {
        return [
            [
                'name' => 'Accounts',
                'rows' => [
                    ['School ID', 'First name', 'Middle name', 'Last name', 'Role', 'Department', 'Password', 'Active'],
                    ['SAMPLE-0001', 'Ana', 'S.', 'Researcher', 'student', 'BSCS', self::DEFAULT_PASSWORD, 'yes'],
                    ['SAMPLE-0002', 'Juan', '', 'Adviser', 'research_adviser', 'BSCS', '', 'yes'],
                ],
            ],
            [
                'name' => 'Instructions',
                'rows' => [
                    ['How to fill the Accounts sheet'],
                    ['One person per row. Do not change the column names in row 1.'],
                    ['Role must be one of: student, research_adviser, dept_research_coordinator, dean, school_research_coordinator, school_administrator.'],
                    ['Aliases: Adviser, Dept Research Coordinator, Dean, School Research Coordinator, School Administrator.'],
                    ['Password must be at least 8 characters. Leave it blank to use ' . self::DEFAULT_PASSWORD . '.'],
                    ['The person is asked to change that password at first sign-in.'],
                    ['Active is yes or no. A blank cell means yes.'],
                    ['School ID, first name, last name, and role are required.'],
                    ['Department is required for a student or research adviser. Use an existing code such as BSCS, or a new code. You can also type CODE - Department name. Leave it blank for other roles.'],
                    ['SAMPLE-0001 and SAMPLE-0002 are examples and are skipped. Replace them with real school IDs.'],
                    ['Save the file as an Excel workbook (.xlsx), then upload it on Accounts.'],
                ],
            ],
        ];
    }

    /**
     * @param list<array{row: int, cells: list<string>}> $sheetRows
     * @param array{program_id?: int, roles?: list<string>} $limit
     * @return array{created: int, notes: list<string>}
     */
    public static function import(array $sheetRows, int $createdBy, array $limit = []): array
    {
        if ($sheetRows === []) {
            throw new RuntimeException('The file is empty. Download the format and add one person per row.');
        }
        $header = array_shift($sheetRows);
        $map = self::headerMap($header['cells']);
        foreach (['school_id', 'first_name', 'last_name', 'role'] as $required) {
            if (!isset($map[$required])) {
                throw new RuntimeException('Use the downloadable format. The first row must include School ID, First name, Last name, and Role.');
            }
        }
        if (count($sheetRows) > self::MAX_ROWS) {
            throw new RuntimeException('Import up to ' . self::MAX_ROWS . ' accounts at a time.');
        }

        $roles = User::roles();
        $allowed = $limit['roles'] ?? [];
        $forceProgram = (int) ($limit['program_id'] ?? 0);
        $notes = [];
        $created = 0;
        $seen = [];
        foreach ($sheetRows as $sheetRow) {
            $cells = $sheetRow['cells'];
            $schoolId = self::cell($cells, $map, 'school_id');
            if ($schoolId === '' || in_array(strtoupper($schoolId), self::SAMPLE_IDS, true)) {
                continue;
            }
            $rowNumber = $sheetRow['row'];
            $problem = self::problem($cells, $map, $schoolId, $roles, $seen, $forceProgram > 0);
            if ($problem !== null) {
                $notes[] = 'Row ' . $rowNumber . ': ' . $problem;
                continue;
            }
            $password = self::cell($cells, $map, 'password');
            if ($password === '') {
                $password = self::DEFAULT_PASSWORD;
            }
            $roleId = self::roleId(self::cell($cells, $map, 'role'), $roles);
            if ($roleId === null) {
                $notes[] = 'Row ' . $rowNumber . ': Role must be a valid system role.';
                continue;
            }
            $slug = self::roleSlug($roleId, $roles);
            if ($allowed !== [] && !in_array($slug, $allowed, true)) {
                $notes[] = 'Row ' . $rowNumber . ': Role is not allowed for this import (' . implode(', ', $allowed) . ').';
                continue;
            }
            $programId = $forceProgram > 0
                ? $forceProgram
                : self::departmentId(
                    self::cell($cells, $map, 'department'),
                    $slug,
                    !empty($limit['create_departments']),
                    !empty($limit['director_departments'])
                );
            if (in_array($slug, ['student', 'research_adviser', 'dept_research_coordinator', 'dean'], true) && !$programId) {
                $notes[] = 'Row ' . $rowNumber . ': Use a department the school administrator added.';
                continue;
            }
            $id = User::create([
                'school_id' => $schoolId,
                'first_name' => self::cell($cells, $map, 'first_name'),
                'middle_name' => self::cell($cells, $map, 'middle_name'),
                'last_name' => self::cell($cells, $map, 'last_name'),
                'role_id' => $roleId,
                'program_id' => $programId,
                'password' => $password,
                'is_active' => self::isActive(self::cell($cells, $map, 'active')),
            ], $createdBy);
            Audit::log('user.create', 'user', $id, ['school_id' => $schoolId, 'source' => 'excel']);
            $seen[strtoupper($schoolId)] = true;
            $created++;
        }

        return ['created' => $created, 'notes' => self::limitNotes($notes)];
    }

    /**
     * @param list<string> $headers
     * @return array<string, int>
     */
    private static function headerMap(array $headers): array
    {
        $aliases = [
            'school id' => 'school_id',
            'schoolid' => 'school_id',
            'id number' => 'school_id',
            'first name' => 'first_name',
            'firstname' => 'first_name',
            'middle name' => 'middle_name',
            'middlename' => 'middle_name',
            'middle' => 'middle_name',
            'last name' => 'last_name',
            'lastname' => 'last_name',
            'surname' => 'last_name',
            'role' => 'role',
            'department' => 'department',
            'program' => 'department',
            'program code' => 'department',
            'password' => 'password',
            'temporary password' => 'password',
            'active' => 'active',
            'status' => 'active',
        ];
        $map = [];
        foreach ($headers as $index => $header) {
            $key = strtolower(trim($header));
            $key = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $key));
            $field = $aliases[$key] ?? null;
            if ($field !== null && !isset($map[$field])) {
                $map[$field] = $index;
            }
        }
        return $map;
    }

    /**
     * @param list<string> $cells
     * @param array<string, int> $map
     */
    private static function cell(array $cells, array $map, string $field): string
    {
        if (!isset($map[$field])) {
            return '';
        }
        return trim($cells[$map[$field]] ?? '');
    }

    /**
     * @param list<string> $cells
     * @param array<string, int> $map
     * @param list<array<string, mixed>> $roles
     * @param array<string, true> $seen
     */
    private static function problem(array $cells, array $map, string $schoolId, array $roles, array $seen, bool $departmentLocked = false): ?string
    {
        if (strlen($schoolId) > 32) {
            return 'School ID must be 32 characters or fewer.';
        }
        if (isset($seen[strtoupper($schoolId)])) {
            return 'School ID ' . $schoolId . ' is listed more than once.';
        }
        if (User::findBySchoolId($schoolId)) {
            return 'School ID ' . $schoolId . ' already has an account.';
        }
        $first = self::cell($cells, $map, 'first_name');
        $last = self::cell($cells, $map, 'last_name');
        $middle = self::cell($cells, $map, 'middle_name');
        if ($first === '' || $last === '') {
            return 'First name and last name are required.';
        }
        if (strlen($first) > 64 || strlen($last) > 64 || strlen($middle) > 64) {
            return 'Each name must be 64 characters or fewer.';
        }
        $roleId = self::roleId(self::cell($cells, $map, 'role'), $roles);
        if ($roleId === null) {
            return 'Role must be a valid system role.';
        }
        $slug = self::roleSlug($roleId, $roles);
        if (!$departmentLocked && in_array($slug, ['student', 'research_adviser', 'dept_research_coordinator', 'dean'], true)) {
            $department = self::cell($cells, $map, 'department');
            if ($department === '' || self::splitDepartment($department) === null) {
                return 'Department is required for this role. Use a code such as BSCS, or CODE - Department name.';
            }
        }
        $password = self::cell($cells, $map, 'password');
        if ($password !== '' && strlen($password) < 8) {
            return 'Password must be at least 8 characters, or leave it blank.';
        }
        $active = strtolower(self::cell($cells, $map, 'active'));
        if ($active !== '' && !in_array($active, ['yes', 'no', 'y', 'n', '1', '0', 'true', 'false', 'active', 'inactive'], true)) {
            return 'Active must be yes or no.';
        }
        return null;
    }

    /** @param list<array<string, mixed>> $roles */
    private static function roleSlug(int $roleId, array $roles): string
    {
        foreach ($roles as $role) {
            if ((int) $role['id'] === $roleId) {
                return (string) $role['slug'];
            }
        }
        return '';
    }

    private static function departmentId(string $value, string $roleSlug, bool $canCreate = false, bool $directorOnly = false): ?int
    {
        if (!in_array($roleSlug, ['student', 'research_adviser', 'dept_research_coordinator', 'dean'], true)) {
            return null;
        }
        $parts = self::splitDepartment($value);
        if ($parts === null) {
            return null;
        }
        $program = Program::findByCodeOrName($parts['code']);
        if ($program) {
            if ($directorOnly && (int) ($program['director_added'] ?? 0) !== 1) {
                return null;
            }
            return (int) $program['id'];
        }
        if (!$canCreate) {
            return null;
        }
        try {
            return Program::create($parts['code'], $parts['name'], true);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @return array{code: string, name: string}|null */
    private static function splitDepartment(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $code = $value;
        $name = $value;
        if (preg_match('/^(.{1,16})\s*[—–-]\s*(.+)$/u', $value, $match)) {
            $code = trim($match[1]);
            $name = trim($match[2]);
        }
        $code = strtoupper($code);
        if (!preg_match('/^[A-Z0-9-]{2,16}$/', $code) || $name === '') {
            return null;
        }
        return ['code' => $code, 'name' => $name];
    }

    /** @param list<array<string, mixed>> $roles */
    private static function roleId(string $value, array $roles): ?int
    {
        $raw = strtolower(trim($value));
        $compact = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $raw));
        $aliases = [
            'student' => 'student',
            'student researcher' => 'student',
            'adviser' => 'research_adviser',
            'advisor' => 'research_adviser',
            'research adviser' => 'research_adviser',
            'research advisor' => 'research_adviser',
            'adviser panelist' => 'research_adviser',
            'librarian' => 'school_research_coordinator',
            'dept research coordinator' => 'dept_research_coordinator',
            'department research coordinator' => 'dept_research_coordinator',
            'dean' => 'dean',
            'school research coordinator' => 'school_research_coordinator',
            'research coordinator' => 'school_research_coordinator',
            'research director' => 'school_administrator',
            'director' => 'school_administrator',
            'school administrator' => 'school_administrator',
            'administrator' => 'school_administrator',
        ];
        $slug = $aliases[$compact] ?? str_replace(' ', '_', $compact);
        foreach ($roles as $role) {
            if (strtolower((string) $role['slug']) === $slug || strtolower((string) $role['name']) === $raw) {
                return (int) $role['id'];
            }
        }
        return null;
    }

    private static function isActive(string $value): int
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['no', 'n', '0', 'false', 'inactive'], true)) {
            return 0;
        }
        return 1;
    }

    /**
     * @param list<string> $notes
     * @return list<string>
     */
    private static function limitNotes(array $notes): array
    {
        if (count($notes) <= 12) {
            return $notes;
        }
        $extra = count($notes) - 12;
        $notes = array_slice($notes, 0, 12);
        $notes[] = 'And ' . $extra . ' more row' . ($extra === 1 ? '' : 's') . '.';
        return $notes;
    }
}
