<?php

namespace App\Support;

final class ApplicationSteps
{
    /** Ordered form steps: slug => label. Courses come after CUET so only eligible courses are offered. */
    public const STEPS = [
        'basic' => 'Basic Details',
        'address' => 'Address',
        'category' => 'Category',
        'academics' => 'Class 10 & 12',
        'cuet' => 'CUET (NTA)',
        'courses' => 'Courses',
        'statements' => 'Statements',
        'documents' => 'Uploads',
        'preview' => 'Preview & Submit',
    ];

    public static function slugs(): array
    {
        return array_keys(self::STEPS);
    }

    public static function index(string $step): int
    {
        return array_search($step, self::slugs(), true);
    }

    public static function next(string $step): ?string
    {
        return self::slugs()[self::index($step) + 1] ?? null;
    }

    public static function previous(string $step): ?string
    {
        $i = self::index($step);

        return $i > 0 ? self::slugs()[$i - 1] : null;
    }

    /** @return string[] steps after the given one */
    public static function after(string $step): array
    {
        return array_slice(self::slugs(), self::index($step) + 1);
    }
}
