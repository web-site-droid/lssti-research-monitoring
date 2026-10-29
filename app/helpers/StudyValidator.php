<?php

declare(strict_types=1);

namespace App\Helpers;

final class StudyValidator
{
    /** @return list<string> */
    public static function validateMetadata(array $input): array
    {
        $errors = [];
        $title = trim($input['title'] ?? '');
        if ($title === '') {
            $errors[] = 'Manuscript title is required.';
        } elseif (strlen($title) > 512) {
            $errors[] = 'Title is too long.';
        } elseif (self::isShouted($title)) {
            $errors[] = 'Write the title in normal capitalization, not all capital letters.';
        }

        $short = trim($input['short_title'] ?? '');
        if ($short === '') {
            $errors[] = 'Short title is required.';
        } elseif (mb_strlen($short) > 80) {
            $errors[] = 'Short title must be at most 80 characters.';
        }

        $abstract = trim($input['abstract'] ?? '');
        $wordCount = self::wordCount($abstract);
        if ($abstract === '') {
            $errors[] = 'Abstract is required.';
        } elseif ($wordCount > 350) {
            $errors[] = 'Abstract must be at most 350 words.';
        }

        if (empty($input['program_id'])) {
            $errors[] = 'Program is required.';
        }
        $passTo = $input['pass_to'] ?? '';
        if (!in_array($passTo, ['teacher', 'library'], true)) {
            $errors[] = 'Choose whether this form is passed to a teacher or to the library.';
        } elseif ($passTo === 'teacher' && empty($input['adviser_user_id'])) {
            $errors[] = 'Choose an adviser when this form is passed to a teacher.';
        }
        if (empty($input['school_year'])) {
            $errors[] = 'School year is required.';
        }
        $type = $input['study_type'] ?? '';
        if (!in_array($type, array_keys(study_type_options()), true)) {
            $errors[] = 'Study type is required.';
        }

        return $errors;
    }

    /** @param list<array{first_name:string,middle_name?:string,last_name:string}> $authors */
    public static function validateAuthors(array $authors): array
    {
        $errors = [];
        if ($authors === []) {
            $errors[] = 'At least one author is required.';
            return $errors;
        }
        foreach ($authors as $i => $a) {
            $fn = trim($a['first_name'] ?? '');
            $ln = trim($a['last_name'] ?? '');
            if ($fn === '' || $ln === '') {
                $errors[] = 'Author ' . ($i + 1) . ' first and last name are required.';
            }
            foreach ([$fn, $ln, trim($a['middle_name'] ?? '')] as $part) {
                if (self::isShouted($part)) {
                    $errors[] = 'Author ' . ($i + 1) . ' is in all capital letters. Initials such as S. are allowed.';
                    break;
                }
            }
        }
        return $errors;
    }

    /** True only when the text has at least 3 letters and every letter is uppercase. */
    public static function isShouted(string $text): bool
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $text) ?? '';
        if (strlen($letters) < 3) {
            return false;
        }
        return $letters === strtoupper($letters);
    }

    public static function wordCount(string $text): int
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return 0;
        }
        return count(preg_split('/\s+/u', $text) ?: []);
    }

    /** @return array{ok:bool,errors:list<string>,keywords:list<string>} */
    public static function parseKeywords(mixed $raw): array
    {
        if (is_array($raw)) {
            $keywords = $raw;
        } else {
            $keywords = preg_split('/[,;]+/', (string) $raw) ?: [];
        }
        $keywords = array_values(array_filter(array_map(static fn ($k) => trim(strtolower((string) $k)), $keywords)));
        $keywords = array_unique($keywords);
        sort($keywords);
        $errors = [];
        return ['ok' => $errors === [], 'errors' => $errors, 'keywords' => $keywords];
    }
}
