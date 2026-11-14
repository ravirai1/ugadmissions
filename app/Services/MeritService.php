<?php

namespace App\Services;

use App\Models\CallLetter;
use App\Models\Course;
use Illuminate\Support\Collection;

/**
 * Merit lists. Total 100 = CUET part (85) + interview part (15).
 * - CUET part: verified CUET score (from academic scrutiny) / maximum for the papers counted x 85.
 * - Interview part: average of the panel members' marks out of 15.
 * Ties: higher CUET part, then higher Class 12 %, then the older applicant (earlier date of birth), then application number.
 * Only candidates marked present with at least one panel mark are ranked.
 */
class MeritService
{
    public static function cuetWeight(): int
    {
        return (int) config('admissions.merit.cuet_weight', 85);
    }

    public static function interviewWeight(): int
    {
        return (int) config('admissions.merit.interview_weight', 15);
    }

    /**
     * @return array{ranked: Collection, pending: Collection, absent: Collection, notMarked: Collection}
     */
    public function forCourse(Course $course): array
    {
        $letters = CallLetter::where('course_id', $course->id)
            ->with(['application.user', 'application.scrutinies', 'application.class12Marks', 'choice', 'marks'])
            ->get();

        $ranked = $letters->filter(fn ($l) => $l->attendance === CallLetter::PRESENT && $l->marks->isNotEmpty())
            ->map(fn ($l) => $this->row($l))
            ->sort(fn ($a, $b) => $this->compare($a, $b))
            ->values();

        $catRank = [];
        $ranked = $ranked->map(function ($r, $i) use (&$catRank) {
            $catRank[$r['category']] = ($catRank[$r['category']] ?? 0) + 1;

            return $r + ['rank' => $i + 1, 'cat_rank' => $catRank[$r['category']]];
        });

        return [
            'ranked' => $ranked,
            'pending' => $letters->filter(fn ($l) => $l->attendance === CallLetter::PRESENT && $l->marks->isEmpty())->values(),
            'absent' => $letters->where('attendance', CallLetter::ABSENT)->values(),
            'notMarked' => $letters->whereNull('attendance')->values(),
        ];
    }

    public function row(CallLetter $l): array
    {
        $app = $l->application;
        $choice = $l->choice;
        $scrutiny = $app->scrutinies->first(fn ($s) => $s->type === 'academic' && $s->application_course_id === $choice->id);
        $raw = (float) ($scrutiny?->verified_cuet_score ?? $choice->cuet_score);
        $max = max(1, count($choice->papers ?? [])) * (int) config('admissions.merit.cuet_paper_max', 250);
        $cuet = round(min($raw, $max) / $max * self::cuetWeight(), 2);
        $interview = $l->interviewScore() ?? 0.0;

        return [
            'letter' => $l,
            'app' => $app,
            'choice' => $choice,
            'cuet_raw' => $raw,
            'cuet_max' => $max,
            'cuet' => $cuet,
            'interview' => $interview,
            'panel' => $l->marks->count(),
            'total' => round($cuet + $interview, 2),
            'category' => $app->meritCategory() ?? 'UR',
            'pwbd' => (bool) $app->is_pwbd,
            'cst' => $app->meritCst(), // as verified by religious scrutiny; never if rejected

            'class12' => (float) ($scrutiny?->verified_class12_percent ?? $app->class12Percentage() ?? 0.0), // verified by the department
            'dob' => $app->dob?->format('Y-m-d') ?? '9999-12-31',
        ];
    }

    /** Sort order: total, CUET part, Class 12 %, older first, application number. */
    public function compare(array $a, array $b): int
    {
        $c = fn (float $x) => (int) round($x * 100);

        return [$c($b['total']), $c($b['cuet']), $c($b['class12']), $a['dob'], $a['app']->application_no]
            <=> [$c($a['total']), $c($a['cuet']), $c($a['class12']), $b['dob'], $b['app']->application_no];
    }

    /** Best total of each applicant across courses, highest first. */
    public function highest(int $limit = 20): Collection
    {
        return Course::orderBy('sort')->get()
            ->flatMap(fn ($c) => $this->forCourse($c)['ranked']->map(fn ($r) => $r + ['course' => $c]))
            ->sort(fn ($a, $b) => $this->compare($a, $b))
            ->unique(fn ($r) => $r['app']->id)
            ->take($limit)
            ->values();
    }
}
