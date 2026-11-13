<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Course;
use Illuminate\Validation\ValidationException;

/**
 * Validates one course chosen by an applicant (eligibility, preferences, language and papers)
 * and turns it into an application_courses row. Used by the form and by "add course after payment".
 */
class CourseSelection
{
    public function __construct(private Eligibility $eligibility) {}

    /** @return array{0: array<string,float>, 1: int[]} CUET scores by subject id, and Class 12 subject ids */
    public function context(Application $app): array
    {
        return [
            $app->cuetScores()->pluck('score', 'subject_id')->map(fn ($v) => (float) $v)->all(),
            $this->eligibility->class12SubjectIds($app),
        ];
    }

    /**
     * @param  array<string,mixed>  $c  the submitted fields for this course
     * @return array{row: array<string,mixed>, prefs: int[]}
     */
    public function build(Application $app, Course $course, array $c, array $scores, array $class12Ids): array
    {
        $eval = $this->eligibility->evaluate($course, $app, $scores, $class12Ids);
        $key = "courses.{$course->id}";
        $prefs = [];

        if (! $eval['eligible']) {
            throw ValidationException::withMessages([$key => "You are not eligible for {$course->name}."]);
        }
        if (empty($c['du_preference'])) {
            throw ValidationException::withMessages(["$key.du_preference" => "Enter your DU CSAS preference number for {$course->name}."]);
        }
        $prefs[] = (int) $c['du_preference'];

        $jmcPref = null;
        $jmcNa = false;
        if ($app->isFemale() && $course->offered_at_jmc) {
            $jmcNa = ! empty($c['jmc_not_applied']);
            if (! $jmcNa && empty($c['jmc_preference'])) {
                throw ValidationException::withMessages(["$key.jmc_preference" => "Enter your JMC preference for {$course->name}, or tick \"Not applied to JMC\"."]);
            }
            $jmcPref = $jmcNa ? null : (int) $c['jmc_preference'];
            if ($jmcPref !== null) {
                $prefs[] = $jmcPref;
            }
        }

        $rules = $course->eligibility;
        $lang = isset($c['language']) ? [(int) $c['language']] : [];
        $isCombo = ! empty($rules['combinations']);
        $combo = $isCombo ? ($c['combination'] ?? null) : ($c['programme'] ?? null);
        $domains = $isCombo
            ? (collect($eval['combinations'])->firstWhere('label', $combo)['papers'] ?? [])
            : array_values(array_map('intval', array_filter($c['domains'] ?? [])));

        if ($error = $this->eligibility->validateSelection($course, $eval, $lang, $domains, $combo)) {
            throw ValidationException::withMessages([$key => "{$course->name}: $error"]);
        }

        $papers = array_merge($lang, $domains);

        return [
            'row' => [
                'course_id' => $course->id,
                'du_preference' => (int) $c['du_preference'],
                'jmc_preference' => $jmcPref,
                'jmc_not_applied' => $jmcNa,
                'combination' => $combo,
                'papers' => $papers,
                'cuet_score' => $this->eligibility->scoreFor($papers, $scores),
            ],
            'prefs' => $prefs,
        ];
    }

    /** @return array<string, array<int, mixed>> validation rules for the course fields */
    public static function rules(): array
    {
        return [
            'courses' => ['required', 'array', 'min:1'],
            'courses.*.selected' => ['nullable'],
            'courses.*.du_preference' => ['nullable', 'integer', 'min:1', 'max:999'],
            'courses.*.jmc_preference' => ['nullable', 'integer', 'min:1', 'max:999'],
            'courses.*.jmc_not_applied' => ['nullable'],
            'courses.*.language' => ['nullable', 'integer'],
            'courses.*.domains' => ['nullable', 'array'],
            'courses.*.domains.*' => ['nullable', 'integer'],
            'courses.*.combination' => ['nullable', 'string', 'max:100'],
            'courses.*.programme' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function assertUniquePrefs(array $prefs): void
    {
        if (count($prefs) !== count(array_unique($prefs))) {
            throw ValidationException::withMessages(['courses' => 'Each preference number must be different (DU CSAS preference numbers are unique).']);
        }
    }
}
