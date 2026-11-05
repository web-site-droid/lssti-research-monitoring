<?php

declare(strict_types=1);

namespace App\Helpers;

final class Workflow
{
    /** @return list<array{label:string,desc:string,route?:string,query?:array,count?:int}> */
    public static function stepsForRole(string $roleSlug): array
    {
        return match ($roleSlug) {
            Role::STUDENT => [
                ['label' => '1. Research adviser', 'desc' => 'Submit your manuscript to your research adviser.', 'route' => 'studies/create'],
                ['label' => '2. Dept research coordinator', 'desc' => 'After adviser approval, send it to the department research coordinator.', 'route' => 'track', 'query' => ['tab' => 'waiting']],
                ['label' => '3. Dean → research coordinator → librarian', 'desc' => 'Track progress through the remaining school review steps.', 'route' => 'track'],
            ],
            Role::RESEARCH_ADVISER => [
                ['label' => '1. Dashboard', 'desc' => 'Assigned advisee papers with full files; other departments show title and abstract only.', 'route' => 'dashboard'],
                ['label' => '2. Review & approve', 'desc' => 'Approve so the student can send it to the dept research coordinator.', 'route' => 'review'],
            ],
            Role::DEPT_RESEARCH_COORDINATOR => [
                ['label' => '1. Department papers', 'desc' => 'Open full files for every study in your department.', 'route' => 'dashboard'],
                ['label' => '2. Send to dean', 'desc' => 'Forward cleared pending studies to the dean. Other departments stay abstract-only.', 'route' => 'dashboard'],
            ],
            Role::DEAN => [
                ['label' => '1. Department papers', 'desc' => 'Open full files for every study in your department.', 'route' => 'dashboard'],
                ['label' => '2. Send to research coordinator', 'desc' => 'Forward dean-cleared studies. Other departments stay abstract-only.', 'route' => 'review'],
            ],
            Role::SCHOOL_RESEARCH_COORDINATOR => [
                ['label' => '1. School-wide dashboard', 'desc' => 'Open full manuscripts from every department.', 'route' => 'dashboard'],
                ['label' => '2. Ethics & plagiarism', 'desc' => 'Record ethics, format, and similarity checks.', 'route' => 'review'],
                ['label' => '3. Send to librarian', 'desc' => 'Forward cleared studies to the librarian.', 'route' => 'review'],
            ],
            Role::LIBRARIAN => [
                ['label' => '1. School-wide dashboard', 'desc' => 'View all research papers from every department with full files.', 'route' => 'dashboard'],
            ],
            Role::SCHOOL_ADMINISTRATOR => [
                ['label' => '1. School-wide dashboard', 'desc' => 'View all research papers from every department with full files.', 'route' => 'dashboard'],
                ['label' => '2. Final publish', 'desc' => 'Publish studies cleared by the school research coordinator.', 'route' => 'review'],
                ['label' => '3. Users & settings', 'desc' => 'Create dean and school research coordinator accounts.', 'route' => 'users'],
            ],
            default => [],
        };
    }

    public static function studyProgressStep(string $status, bool $skipAdviser = false): int
    {
        if ($skipAdviser) {
            return match ($status) {
                'draft' => 1,
                'submitted', 'adviser_reviewed', 'revision_requested', 'resubmitted', 'ethics_review', 'pending' => 2,
                'dean_review' => 3,
                'under_review' => 4,
                'library_review', 'admin_review', 'archived' => 5,
                'approved' => 6,
                default => 1,
            };
        }
        return match ($status) {
            'draft' => 1,
            'submitted', 'adviser_reviewed', 'revision_requested', 'resubmitted', 'ethics_review' => 2,
            'pending' => 3,
            'dean_review' => 4,
            'under_review' => 5,
            'library_review', 'admin_review', 'archived' => 6,
            'approved' => 7,
            default => 1,
        };
    }

    public static function studyReviewLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'With dept research coordinator',
            'dean_review' => 'With dean',
            'under_review' => 'With school research coordinator',
            'library_review' => 'With librarian',
            'admin_review' => 'With librarian',
            default => 'In review',
        };
    }

    /** @return list<string> */
    public static function progressLabels(bool $skipAdviser = false): array
    {
        return $skipAdviser
            ? ['Prepare', 'Dept', 'Dean', 'Research Coord', 'Librarian', 'Publish']
            : ['Prepare', 'Adviser', 'Dept', 'Dean', 'Research Coord', 'Librarian', 'Publish'];
    }
}
