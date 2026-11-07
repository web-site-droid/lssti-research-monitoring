<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers/functions.php';
require dirname(__DIR__) . '/app/helpers/StudyValidator.php';

$metadata = [
    'title' => 'Sample research', 'short_title' => 'Sample', 'abstract' => 'Sample abstract.',
    'program_id' => 1, 'pass_to' => 'teacher', 'adviser_user_id' => 2,
    'school_year' => '2026-2027', 'study_type' => 'thesis',
];
$cases = [
    ['', false], [null, false], [[], false], ['2026-02-30', false],
    ['2025-02-29', false], ['2024-02-29', true], ['2026-1-02', false],
    ['0000-01-01', false], ['01/02/2026', false], ["2026-01-01\0", false], [date('Y-m-d'), true],
    [(new DateTimeImmutable('tomorrow'))->format('Y-m-d'), false],
];
foreach ($cases as [$date, $valid]) {
    $errors = \App\Helpers\StudyValidator::validateMetadata($metadata + ['upload_date' => $date]);
    if (($errors === []) !== $valid) {
        throw new RuntimeException('Unexpected upload date validation: ' . json_encode([$date, $errors]));
    }
}
if (!in_array('Upload date is required.', \App\Helpers\StudyValidator::validateMetadata($metadata), true)) {
    throw new RuntimeException('Missing upload date must be rejected.');
}
echo "Passed 13 upload date validation checks.\n";
