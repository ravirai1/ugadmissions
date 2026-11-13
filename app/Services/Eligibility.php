<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * DU-style eligibility and CUET score calculation.
 *
 * Course eligibility JSON:
 * {
 *   "class12_required": ["MAT"],             // CUET subject codes that must appear among Class 12 subjects
 *   "min_class12_percent": 50,                // optional
 *   "languages": {"count": 1, "allowed": ["ENG"]}, // allowed null = any language
 *   "domains": {"count": 2, "allowed": null, "must_include": ["MAT"]},
 *   "general_test": false,                    // whether the General Test may be counted in place of a domain
 *   "combinations": [{"label": "PCM", "papers": ["PHY","CHE","MAT"]}], // if set, applicant picks one; replaces "domains"
 *   "programme_options": ["Economics + History"] // B.A. Programme discipline choices
 * }
 */
class Eligibility
{
    /** @var Collection<int,Subject> keyed by id */
    private Collection $subjects;

    public function __construct()
    {
        $this->subjects = Subject::all()->keyBy('id');
    }

    /** CUET subject ids covered by the applicant's Class 12 subjects. */
    public function class12SubjectIds(Application $app): array
    {
        return $app->class12Marks()->with('class12Subject')->get()
            ->pluck('class12Subject.subject_id')->filter()->unique()->values()->all();
    }

    /**
     * CUET domain papers the applicant took that are NOT backed by a Class 12 subject.
     *
     * @param  array<int,int>  $cuetSubjectIds
     * @return Subject[]
     */
    public function unmatchedDomainPapers(array $cuetSubjectIds, array $class12SubjectIds): array
    {
        return collect($cuetSubjectIds)
            ->map(fn ($id) => $this->subjects[$id] ?? null)
            ->filter(fn ($s) => $s && $s->type === Subject::DOMAIN && ! in_array($s->id, $class12SubjectIds, true))
            ->values()->all();
    }

    /**
     * Evaluates one course for an applicant.
     *
     * @return array{eligible: bool, reasons: string[], languages: Subject[], domains: Subject[], general: ?Subject, combinations: array, best: array}
     */
    public function evaluate(Course $course, Application $app, ?array $scores = null, ?array $class12Ids = null): array
    {
        $rules = $course->eligibility ?? [];
        $scores ??= $app->cuetScores()->pluck('score', 'subject_id')->map(fn ($v) => (float) $v)->all();
        $class12Ids ??= $this->class12SubjectIds($app);
        $code = fn (string $c) => $this->subjects->firstWhere('code', $c);
        $reasons = [];

        foreach ($rules['class12_required'] ?? [] as $req) {
            $s = $code($req);
            if (! $s || ! in_array($s->id, $class12Ids, true)) {
                $reasons[] = 'Requires '.($s->name ?? $req).' in Class 12';
            }
        }

        if (! empty($rules['min_class12_percent'])) {
            $pct = $app->class12Percentage();
            if ($pct === null || $pct < $rules['min_class12_percent']) {
                $reasons[] = 'Requires at least '.$rules['min_class12_percent'].'% in Class 12';
            }
        }

        $taken = collect(array_keys($scores))->map(fn ($id) => $this->subjects[$id] ?? null)->filter();

        // Languages
        $langRule = $rules['languages'] ?? ['count' => 1, 'allowed' => null];
        $langs = $taken->where('type', Subject::LANGUAGE)
            ->filter(fn ($s) => empty($langRule['allowed']) || in_array($s->code, $langRule['allowed'], true))
            ->values();
        if ($langs->count() < ($langRule['count'] ?? 1)) {
            $reasons[] = empty($langRule['allowed'])
                ? 'Requires a CUET language paper'
                : 'Requires CUET language: '.collect($langRule['allowed'])->map(fn ($c) => $code($c)?->name ?? $c)->implode(' / ');
        }

        // Domains (only papers backed by Class 12 count)
        $domainRule = $rules['domains'] ?? ['count' => 0];
        $domains = $taken->where('type', Subject::DOMAIN)
            ->filter(fn ($s) => in_array($s->id, $class12Ids, true))
            ->filter(fn ($s) => empty($domainRule['allowed']) || in_array($s->code, $domainRule['allowed'], true))
            ->values();
        $general = $taken->firstWhere('type', Subject::GENERAL);

        $combos = [];
        if (! empty($rules['combinations'])) {
            foreach ($rules['combinations'] as $combo) {
                $ids = [];
                foreach ($combo['papers'] as $c) {
                    $s = $code($c);
                    if ($s && isset($scores[$s->id]) && ($s->type !== Subject::DOMAIN || in_array($s->id, $class12Ids, true))) {
                        $ids[] = $s->id;
                    }
                }
                if (count($ids) === count($combo['papers'])) {
                    $combos[] = ['label' => $combo['label'], 'papers' => $ids];
                }
            }
            if (! $combos) {
                $reasons[] = 'Requires one of these CUET combinations: '.collect($rules['combinations'])->pluck('label')->implode(', ');
            }
        } else {
            $needed = $domainRule['count'] ?? 0;
            $available = $domains->count() + (! empty($rules['general_test']) && $general ? 1 : 0);
            if ($available < $needed) {
                $reasons[] = "Requires $needed CUET domain paper(s)".(empty($domainRule['allowed']) ? '' : ' from: '.collect($domainRule['allowed'])->map(fn ($c) => $code($c)?->name ?? $c)->implode(', '));
            }
            foreach ($domainRule['must_include'] ?? [] as $c) {
                $s = $code($c);
                if (! $s || ! $domains->contains('id', $s->id)) {
                    $reasons[] = 'Requires CUET '.($s->name ?? $c).' (also studied in Class 12)';
                }
            }
        }

        $result = [
            'eligible' => ! $reasons,
            'reasons' => array_values(array_unique($reasons)),
            'languages' => $langs->all(),
            'domains' => $domains->all(),
            'general' => (! empty($rules['general_test']) ? $general : null),
            'combinations' => $combos,
        ];
        $result['best'] = $result['eligible'] ? $this->best($rules, $result, $scores) : [];

        return $result;
    }

    /** Highest-scoring valid selection: ['language' => [ids], 'domains' => [ids], 'combination' => ?label]. */
    private function best(array $rules, array $eval, array $scores): array
    {
        $by = fn ($a, $b) => ($scores[$b->id] ?? 0) <=> ($scores[$a->id] ?? 0);
        $langs = collect($eval['languages'])->sort($by)->take($rules['languages']['count'] ?? 1)->pluck('id')->all();

        if ($eval['combinations']) {
            $bestCombo = collect($eval['combinations'])
                ->sortByDesc(fn ($c) => array_sum(array_map(fn ($id) => $scores[$id] ?? 0, $c['papers'])))
                ->first();

            return ['language' => $langs, 'domains' => $bestCombo['papers'], 'combination' => $bestCombo['label']];
        }

        $pool = collect($eval['domains']);
        if ($eval['general']) {
            $pool->push($eval['general']);
        }
        $must = collect($rules['domains']['must_include'] ?? [])->map(fn ($c) => $this->subjects->firstWhere('code', $c)?->id)->filter();
        $count = $rules['domains']['count'] ?? 0;
        $chosen = $must->values();
        foreach ($pool->sort($by) as $s) {
            if ($chosen->count() >= $count) {
                break;
            }
            if (! $chosen->contains($s->id)) {
                $chosen->push($s->id);
            }
        }

        return ['language' => $langs, 'domains' => $chosen->all(), 'combination' => null];
    }

    /**
     * Validates an applicant's chosen papers for a course.
     *
     * @return string|null error message, or null if valid
     */
    public function validateSelection(Course $course, array $eval, array $languageIds, array $domainIds, ?string $combination): ?string
    {
        $rules = $course->eligibility ?? [];
        $langAllowed = collect($eval['languages'])->pluck('id')->all();
        $langCount = $rules['languages']['count'] ?? 1;

        if (count(array_unique($languageIds)) !== $langCount || array_diff($languageIds, $langAllowed)) {
            return "Choose $langCount valid language paper(s).";
        }

        if (! empty($rules['combinations'])) {
            $combo = collect($eval['combinations'])->firstWhere('label', $combination);

            return $combo ? null : 'Choose a valid subject combination.';
        }

        $pool = collect($eval['domains'])->pluck('id');
        if ($eval['general']) {
            $pool->push($eval['general']->id);
        }
        $count = $rules['domains']['count'] ?? 0;

        if (count(array_unique($domainIds)) !== $count || array_diff($domainIds, $pool->all())) {
            return "Choose $count different valid domain paper(s).";
        }

        foreach ($rules['domains']['must_include'] ?? [] as $c) {
            $id = $this->subjects->firstWhere('code', $c)?->id;
            if ($id && ! in_array($id, $domainIds, true)) {
                return 'Your selection must include '.$this->subjects[$id]->name.'.';
            }
        }

        if (! empty($rules['programme_options']) && ! in_array($combination, $rules['programme_options'], true)) {
            return 'Choose a valid discipline combination.';
        }

        return null;
    }

    public function scoreFor(array $paperIds, array $scores): float
    {
        return round(array_sum(array_map(fn ($id) => $scores[$id] ?? 0, $paperIds)), 2);
    }
}
