<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Category;
use App\Models\Class12Subject;
use App\Models\Course;
use App\Models\District;
use App\Models\PwbdType;
use App\Models\State;
use App\Models\Subject;
use App\Notifications\ApplicationSubmitted;
use App\Services\CourseSelection;
use App\Services\DocumentStore;
use App\Services\Eligibility;
use App\Support\ApplicationSteps;
use App\Support\DocumentTypes;
use App\Support\PersonalDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicationController extends Controller
{
    public const INCOME_RANGES = [
        'below_2.5' => 'Below ₹2.5 lakh',
        '2.5_5' => '₹2.5 – 5 lakh',
        '5_8' => '₹5 – 8 lakh',
        '8_12' => '₹8 – 12 lakh',
        '12_20' => '₹12 – 20 lakh',
        'above_20' => 'Above ₹20 lakh',
    ];

    public const GENDERS = ['Male', 'Female', 'Transgender'];

    /** Baptism fields asked for each parent when the applicant is not baptised. */
    private const PARENT_BAPTISM = ['baptism_date', 'baptism_church', 'baptism_place', 'baptism_priest'];

    public function __construct(private Eligibility $eligibility, private CourseSelection $selection) {}

    private function app(Request $request): Application
    {
        return $request->user()->application()->firstOrCreate([]);
    }

    /** First step the applicant has not completed. */
    private function resumeStep(Application $app): string
    {
        foreach (ApplicationSteps::slugs() as $step) {
            if ($step !== 'preview' && ! $app->hasCompleted($step)) {
                return $step;
            }
        }

        return 'preview';
    }

    private function canOpen(Application $app, string $step): bool
    {
        foreach (array_slice(ApplicationSteps::slugs(), 0, ApplicationSteps::index($step)) as $prev) {
            if (! $app->hasCompleted($prev)) {
                return false;
            }
        }

        return true;
    }

    public function start(Request $request)
    {
        $app = $this->app($request);

        return redirect()->route('application.step', $app->isSubmitted() ? 'preview' : $this->resumeStep($app));
    }

    public function show(Request $request, string $step)
    {
        $app = $this->app($request);

        if ($app->isSubmitted() && $step !== 'preview') {
            return redirect()->route('application.step', 'preview');
        }
        if (! $this->canOpen($app, $step)) {
            return redirect()->route('application.step', $this->resumeStep($app))
                ->with('status', 'Please complete this step first.');
        }

        return view('application.'.$step, array_merge(
            ['app' => $app, 'step' => $step, 'user' => $request->user()],
            $this->viewData($app, $step)
        ));
    }

    private function viewData(Application $app, string $step): array
    {
        return match ($step) {
            'basic' => ['incomes' => self::INCOME_RANGES, 'genders' => self::GENDERS, 'pd' => PersonalDetails::class],
            'address' => [
                'states' => State::where('is_active', true)->orderBy('sort')->orderBy('name')->get(['id', 'name']),
                'districts' => District::where('is_active', true)->orderBy('name')->get(['id', 'state_id', 'name'])->groupBy('state_id')
                    ->map(fn ($g) => $g->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values()),
            ],
            'category' => [
                'categories' => Category::whereNull('parent_code')->where('is_active', true)->orderBy('sort')->get(),
                'subcategories' => Category::where('parent_code', Category::CHRISTIAN)->where('is_active', true)->orderBy('sort')->get(),
                'pwbdTypes' => PwbdType::where('is_active', true)->orderBy('group_no')->get()->groupBy('group_name'),
                'baptismYears' => (int) config('admissions.baptism_min_years', 2),
            ],
            'academics' => [
                'class12Subjects' => Class12Subject::where('is_active', true)->orderBy('name')->get(),
                'marks' => $app->class12Marks()->get(),
            ],
            'cuet' => [
                'subjects' => Subject::where('is_active', true)->orderBy('sort')->get()->groupBy('type'),
                'scores' => $app->cuetScores()->get(),
                'class12Names' => $app->class12Marks()->with('class12Subject.subject')->get(),
            ],
            'courses' => $this->coursesData($app),
            'statements' => ['chosen' => $app->courses()->with('course')->get()],
            'documents' => [
                'required' => $app->requiredDocumentTypes(),
                'uploaded' => $app->documents()->get()->keyBy('type'),
            ],
            'preview' => $this->previewData($app),
            default => [],
        };
    }

    private function coursesData(Application $app): array
    {
        $scores = $app->cuetScores()->pluck('score', 'subject_id')->map(fn ($v) => (float) $v)->all();
        $class12Ids = $this->eligibility->class12SubjectIds($app);
        $courses = Course::where('is_active', true)->orderBy('sort')->get();

        return [
            'courses' => $courses,
            'evals' => $courses->mapWithKeys(fn ($c) => [$c->id => $this->eligibility->evaluate($c, $app, $scores, $class12Ids)]),
            'scores' => $scores,
            'chosen' => $app->courses()->get()->keyBy('course_id'),
            'subjects' => Subject::all()->keyBy('id'),
        ];
    }

    private function previewData(Application $app): array
    {
        $app->load(['class12Marks.class12Subject', 'cuetScores.subject', 'courses.course', 'curState', 'curDistrict', 'permState', 'permDistrict', 'domicileState', 'pwbdType']);

        return [
            'incomes' => self::INCOME_RANGES,
            'categoryNames' => Category::pluck('name', 'code'),
            'subjects' => Subject::all()->keyBy('id'),
            'uploaded' => $app->documents()->get()->keyBy('type'),
            'required' => $app->requiredDocumentTypes(),
            'missing' => $this->missingItems($app),
        ];
    }

    /** @return string[] */
    private function missingItems(Application $app): array
    {
        $missing = [];
        foreach (ApplicationSteps::STEPS as $slug => $label) {
            if ($slug !== 'preview' && ! $app->hasCompleted($slug)) {
                $missing[] = "Step not completed: $label";
            }
        }
        $have = $app->documents()->pluck('type')->all();
        foreach (array_diff($app->requiredDocumentTypes(), $have) as $type) {
            $missing[] = 'Document missing: '.DocumentTypes::label($type);
        }

        return $missing;
    }

    public function save(Request $request, string $step)
    {
        $app = $this->app($request);
        abort_if($app->isSubmitted(), 403, 'Your application has already been submitted.');
        if (! $this->canOpen($app, $step)) {
            return redirect()->route('application.step', $this->resumeStep($app));
        }

        $method = 'save'.ucfirst($step);
        abort_unless(method_exists($this, $method) && $step !== 'preview', 404);

        DB::transaction(fn () => $this->{$method}($request, $app));
        $app->markCompleted($step);

        return redirect()->route('application.step', ApplicationSteps::next($step))
            ->with('status', ApplicationSteps::STEPS[$step].' saved.');
    }

    private function saveBasic(Request $request, Application $app): void
    {
        $name = ['required', 'string', 'max:100', 'regex:/^[\pL\s.\'-]+$/u'];
        $rules = [
            'dob' => ['required', 'date', 'before:2012-01-01', 'after:1990-01-01'],
            'gender' => ['required', Rule::in(self::GENDERS)],
            'blood_group' => ['required', Rule::in(PersonalDetails::BLOOD_GROUPS)],
            'nationality' => ['required', Rule::in(PersonalDetails::NATIONALITIES)],
            'mother_tongue' => ['required', Rule::in(PersonalDetails::MOTHER_TONGUES)],
            'mother_tongue_other' => ['nullable', 'required_if:mother_tongue,Other', 'string', 'max:60'],
            'family_income' => ['required', Rule::in(array_keys(self::INCOME_RANGES))],
            'last_school' => ['required', 'string', 'max:200'],
            'last_school_city' => ['required', 'string', 'max:100'],
        ];
        $messages = ['mother_tongue_other.required_if' => 'Enter your mother tongue.'];
        foreach (['father' => "Father's", 'mother' => "Mother's"] as $p => $label) {
            $late = $request->input("{$p}_title") === PersonalDetails::DECEASED;
            $working = ! $late && in_array($request->input("{$p}_occupation"), PersonalDetails::WORKING, true);
            $rules += [
                "{$p}_title" => ['required', Rule::in(PersonalDetails::TITLES)],
                "{$p}_name" => $name,
                "{$p}_qualification" => ['required', Rule::in(PersonalDetails::QUALIFICATIONS)],
                "{$p}_occupation" => [$late ? 'nullable' : 'required', Rule::in(PersonalDetails::occupations())],
                "{$p}_designation" => [$working ? 'required' : 'nullable', 'string', 'max:100'],
                "{$p}_organisation" => [$working ? 'required' : 'nullable', 'string', 'max:150'],
                "{$p}_office_address" => [$working ? 'required' : 'nullable', 'string', 'max:300'],
                "{$p}_mobile" => ['nullable', 'regex:/^[6-9][0-9]{9}$/'],
                "{$p}_email" => ['nullable', 'email:rfc', 'max:150'],
            ];
            $messages += [
                "{$p}_designation.required" => "Enter the {$label} designation.",
                "{$p}_organisation.required" => 'Enter where the '.strtolower(rtrim($label, "'s")).' works.',
                "{$p}_office_address.required" => "Enter the {$label} office address.",
                "{$p}_mobile.regex" => 'Enter a valid 10-digit mobile number.',
            ];
        }
        $request->merge(collect(['father_mobile', 'mother_mobile'])->mapWithKeys(fn ($f) => [$f => preg_replace('/\D/', '', (string) $request->input($f)) ?: null])->all());
        $data = $request->validate($rules, $messages);
        if (empty($data['father_mobile']) && empty($data['mother_mobile'])) {
            throw ValidationException::withMessages(['father_mobile' => 'Give a mobile number for at least one parent.']);
        }
        if ($data['mother_tongue'] !== 'Other') {
            $data['mother_tongue_other'] = null;
        }
        foreach (['father', 'mother'] as $p) {
            // clear work details that no longer apply
            if (($data["{$p}_title"] === PersonalDetails::DECEASED) || ! in_array($data["{$p}_occupation"] ?? null, PersonalDetails::WORKING, true)) {
                $data["{$p}_designation"] = $data["{$p}_organisation"] = $data["{$p}_office_address"] = null;
            }
            if ($data["{$p}_title"] === PersonalDetails::DECEASED) {
                $data["{$p}_occupation"] = $data["{$p}_mobile"] = $data["{$p}_email"] = null;
            }
        }

        $genderChanged = $app->gender && $app->gender !== $data['gender'];
        $app->fill($data)->save();

        // JMC preference questions depend on gender.
        if ($genderChanged) {
            $app->invalidate('courses');
        }
    }

    private function saveAddress(Request $request, Application $app): void
    {
        $request->merge(['perm_same' => $request->boolean('perm_same')]);
        $addr = fn (string $p, bool $req) => [
            "{$p}_line1" => [$req ? 'required' : 'nullable', 'string', 'max:200'],
            "{$p}_line2" => ['nullable', 'string', 'max:200'],
            "{$p}_city" => [$req ? 'required' : 'nullable', 'string', 'max:100'],
            "{$p}_state_id" => [$req ? 'required' : 'nullable', Rule::exists('states', 'id')->where('is_active', true)],
            "{$p}_district_id" => ['nullable', Rule::exists('districts', 'id')->where(fn ($q) => $q->where('state_id', $request->input("{$p}_state_id")))],
            "{$p}_district_other" => ['nullable', 'string', 'max:100'],
            "{$p}_pin" => [$req ? 'required' : 'nullable', 'regex:/^[1-9][0-9]{5}$/'],
        ];

        $data = $request->validate(array_merge(
            $addr('cur', true),
            $addr('perm', ! $request->boolean('perm_same')),
            ['perm_same' => ['boolean'], 'domicile_state_id' => ['required', Rule::exists('states', 'id')->where('is_active', true)]]
        ), ['*_pin.regex' => 'Enter a valid 6-digit PIN code.']);

        foreach (['cur', 'perm'] as $p) {
            if (! $data['perm_same'] || $p === 'cur') {
                $hasDistricts = District::where('state_id', $data["{$p}_state_id"])->exists();
                if ($hasDistricts && empty($data["{$p}_district_id"])) {
                    throw ValidationException::withMessages(["{$p}_district_id" => 'Select a district.']);
                }
                if (! $hasDistricts && empty($data["{$p}_district_other"])) {
                    throw ValidationException::withMessages(["{$p}_district_other" => 'Enter your district.']);
                }
            }
        }

        if ($data['perm_same']) {
            foreach (['line1', 'line2', 'city', 'state_id', 'district_id', 'district_other', 'pin'] as $f) {
                $data["perm_$f"] = $data["cur_$f"] ?? null;
            }
        }

        $app->fill($data)->save();
    }

    private function saveCategory(Request $request, Application $app): void
    {
        $request->merge(['is_cst' => $request->boolean('is_cst'), 'is_pwbd' => $request->boolean('is_pwbd'), 'needs_scribe' => $request->boolean('needs_scribe')]);
        $christian = $request->input('category_code') === Category::CHRISTIAN;
        // Christian applicants who are not baptised themselves give a parent's baptism details instead.
        $self = $christian && $request->input('baptised') !== 'no';
        $parentGiven = fn (string $p) => $christian && ! $self && collect(self::PARENT_BAPTISM)
            ->contains(fn ($f) => filled($request->input("{$p}_{$f}")));
        // Baptism must be at least N years before the application.
        $years = (int) config('admissions.baptism_min_years', 2);
        $latestBaptism = now()->subYears($years)->toDateString();
        $request->merge(['current_priest_mobile' => preg_replace('/\D/', '', (string) $request->input('current_priest_mobile')) ?: null]);

        $data = $request->validate([
            'category_code' => ['required', Rule::exists('categories', 'code')->whereNull('parent_code')->where('is_active', true)],
            'christian_subcategory' => [Rule::requiredIf($christian), 'nullable', Rule::exists('categories', 'code')->where('parent_code', Category::CHRISTIAN)],
            'is_cst' => ['boolean'],
            'baptised' => ['nullable', Rule::in(['yes', 'no'])], // missing means yes
            'baptism_date' => [Rule::requiredIf($self), 'nullable', 'date', 'before_or_equal:'.$latestBaptism],
            'baptism_church' => [Rule::requiredIf($self), 'nullable', 'string', 'max:200'],
            'baptism_place' => [Rule::requiredIf($self), 'nullable', 'string', 'max:100'],
            'baptism_priest' => [Rule::requiredIf($self), 'nullable', 'string', 'max:100'],
            'baptism_priest_designation' => ['nullable', 'string', 'max:100'],
            'denomination' => [Rule::requiredIf($christian), 'nullable', 'string', 'max:100'],
            'current_church' => [Rule::requiredIf($christian), 'nullable', 'string', 'max:200'],
            'current_priest' => [Rule::requiredIf($christian), 'nullable', 'string', 'max:100'],
            'current_church_address' => [Rule::requiredIf($christian), 'nullable', 'string', 'max:300'],
            'current_priest_mobile' => [Rule::requiredIf($christian), 'nullable', 'regex:/^[6-9][0-9]{9}$/'],
            'current_priest_email' => ['nullable', 'email:rfc', 'max:150'],
            'father_baptism_date' => [Rule::requiredIf($parentGiven('father')), 'nullable', 'date', 'before_or_equal:'.$latestBaptism],
            'father_baptism_church' => [Rule::requiredIf($parentGiven('father')), 'nullable', 'string', 'max:200'],
            'father_baptism_place' => [Rule::requiredIf($parentGiven('father')), 'nullable', 'string', 'max:100'],
            'father_baptism_priest' => [Rule::requiredIf($parentGiven('father')), 'nullable', 'string', 'max:100'],
            'mother_baptism_date' => [Rule::requiredIf($parentGiven('mother')), 'nullable', 'date', 'before_or_equal:'.$latestBaptism],
            'mother_baptism_church' => [Rule::requiredIf($parentGiven('mother')), 'nullable', 'string', 'max:200'],
            'mother_baptism_place' => [Rule::requiredIf($parentGiven('mother')), 'nullable', 'string', 'max:100'],
            'mother_baptism_priest' => [Rule::requiredIf($parentGiven('mother')), 'nullable', 'string', 'max:100'],
            'is_pwbd' => ['boolean'],
            'pwbd_type_id' => [Rule::requiredIf($request->boolean('is_pwbd')), 'nullable', Rule::exists('pwbd_types', 'id')->where('is_active', true)],
            'pwbd_percentage' => [Rule::requiredIf($request->boolean('is_pwbd')), 'nullable', 'integer', 'min:40', 'max:100'],
            'udid_no' => [Rule::requiredIf($request->boolean('is_pwbd')), 'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/'],
            'needs_scribe' => ['boolean'],
        ], [
            'pwbd_percentage.min' => 'PwBD category requires a benchmark disability of at least 40%.',
            '*baptism_date.before_or_equal' => "Baptism must be at least {$years} years before the date of application (on or before ".now()->subYears($years)->format('d M Y').').',
            'current_priest_mobile.required' => "Enter the parish priest's mobile number.",
            'current_priest_mobile.regex' => 'Enter a valid 10-digit mobile number.',
        ]);
        if ($christian && ! $self && ! $parentGiven('father') && ! $parentGiven('mother')) {
            throw ValidationException::withMessages(['father_baptism_date' => 'Give the baptism details of at least one parent.']);
        }

        $docsBefore = $app->requiredDocumentTypes();
        $app->category_code = $data['category_code'];
        $app->christian_subcategory = $christian ? $data['christian_subcategory'] : null;
        $app->is_cst = $christian && $data['is_cst'];
        $keep = ['baptised', 'denomination', 'current_church', 'current_church_address', 'current_priest', 'current_priest_mobile', 'current_priest_email'];
        if ($self) {
            array_push($keep, 'baptism_date', 'baptism_church', 'baptism_place', 'baptism_priest', 'baptism_priest_designation');
        } else {
            foreach (['father', 'mother'] as $p) {
                if ($parentGiven($p)) {
                    foreach (self::PARENT_BAPTISM as $f) {
                        $keep[] = "{$p}_{$f}";
                    }
                }
            }
        }
        $app->baptism_details = $christian ? ['baptised' => $self ? 'yes' : 'no'] + collect($data)->only($keep)->all() : null;
        $app->is_pwbd = $data['is_pwbd'];
        $app->pwbd_type_id = $data['is_pwbd'] ? $data['pwbd_type_id'] : null;
        $app->pwbd_percentage = $data['is_pwbd'] ? $data['pwbd_percentage'] : null;
        $app->udid_no = $data['is_pwbd'] ? strtoupper($data['udid_no']) : null;
        $app->needs_scribe = $data['is_pwbd'] && $data['needs_scribe'];
        $app->save();

        if (array_diff($app->requiredDocumentTypes(), $docsBefore)) {
            $app->invalidate('documents');
        }
    }

    private function saveAcademics(Request $request, Application $app): void
    {
        $year = (int) date('Y');
        $data = $request->validate([
            'c10_board' => ['required', 'string', 'max:100'],
            'c10_year' => ['required', 'integer', 'min:2015', 'max:'.$year],
            'c10_roll_no' => ['required', 'string', 'max:30'],
            'c10_max' => ['required', 'integer', 'min:100', 'max:1000'],
            'c10_obtained' => ['required', 'numeric', 'min:0', 'lte:c10_max'],
            'c12_board' => ['required', 'string', 'max:100'],
            'c12_year' => ['required', 'integer', 'min:2017', 'max:'.($year + 1), 'gte:c10_year'],
            'c12_roll_no' => ['required', 'string', 'max:30'],
            'marks' => ['required', 'array', 'min:5', 'max:8'],
            'marks.*.subject' => ['required', 'distinct', Rule::exists('class12_subjects', 'id')->where('is_active', true)],
            'marks.*.max' => ['required', 'integer', 'min:1', 'max:200'],
            'marks.*.obtained' => ['required', 'numeric', 'min:0'],
        ], [
            'marks.min' => 'Enter at least 5 Class 12 subjects.',
            'marks.*.subject.distinct' => 'Each subject can be entered only once.',
        ]);

        foreach ($data['marks'] as $i => $m) {
            if ($m['obtained'] > $m['max']) {
                throw ValidationException::withMessages(["marks.$i.obtained" => 'Marks obtained cannot exceed maximum marks.']);
            }
        }

        $before = $app->class12Marks()->pluck('class12_subject_id')->sort()->values()->all();
        $app->fill(collect($data)->except('marks')->all())->save();
        $app->class12Marks()->delete();
        foreach ($data['marks'] as $m) {
            $app->class12Marks()->create(['class12_subject_id' => $m['subject'], 'max_marks' => $m['max'], 'marks_obtained' => $m['obtained']]);
        }

        // CUET matching depends on the Class 12 subjects; eligibility (min %) on the marks.
        $after = collect($data['marks'])->pluck('subject')->map(fn ($v) => (int) $v)->sort()->values()->all();
        if ($before !== $after) {
            $app->invalidate('cuet', 'courses', 'statements');
        } elseif ($app->hasCompleted('courses')) {
            $app->invalidate('courses');
        }
    }

    private function saveCuet(Request $request, Application $app): void
    {
        $data = $request->validate([
            'cuet_roll_no' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'scores' => ['required', 'array', 'min:2', 'max:7'],
            'scores.*.subject' => ['required', 'distinct', Rule::exists('subjects', 'id')->where('is_active', true)],
            'scores.*.score' => ['required', 'numeric', 'min:0', 'max:250'],
        ], ['scores.*.subject.distinct' => 'Each CUET paper can be entered only once.']);

        $ids = array_map('intval', array_column($data['scores'], 'subject'));
        $subjects = Subject::whereIn('id', $ids)->get();
        if (! $subjects->contains('type', Subject::LANGUAGE)) {
            throw ValidationException::withMessages(['scores' => 'Enter at least one CUET language paper.']);
        }

        $unmatched = $this->eligibility->unmatchedDomainPapers($ids, $this->eligibility->class12SubjectIds($app));
        if ($unmatched) {
            throw ValidationException::withMessages(['scores' => collect($unmatched)->map(
                fn ($s) => "You took CUET {$s->name} but did not list a matching subject in Class 12. Correct your Class 12 subjects or remove this paper."
            )->all()]);
        }

        $before = $app->cuetScores()->orderBy('subject_id')->get()->map(fn ($r) => $r->subject_id.':'.(float) $r->score)->all();
        $app->cuet_roll_no = strtoupper($data['cuet_roll_no']);
        $app->save();
        $app->cuetScores()->delete();
        foreach ($data['scores'] as $s) {
            $app->cuetScores()->create(['subject_id' => $s['subject'], 'score' => $s['score']]);
        }

        // Course eligibility and scores depend on CUET papers and scores.
        $after = collect($data['scores'])->sortBy('subject')->map(fn ($r) => (int) $r['subject'].':'.(float) $r['score'])->values()->all();
        if ($before !== $after) {
            $app->invalidate('courses');
        }
    }

    private function saveCourses(Request $request, Application $app): void
    {
        $request->merge(['residence' => $request->boolean('residence')]);
        $data = $request->validate([
            'csas_application_no' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'residence' => ['boolean'],
        ] + CourseSelection::rules());

        $selected = collect($data['courses'])->filter(fn ($c) => ! empty($c['selected']));
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['courses' => 'Select at least one course.']);
        }

        [$scores, $class12Ids] = $this->selection->context($app);
        $prefs = [];
        $rows = [];

        foreach ($selected as $courseId => $c) {
            $course = Course::where('is_active', true)->findOrFail($courseId);
            $built = $this->selection->build($app, $course, $c, $scores, $class12Ids);
            $rows[] = $built['row'];
            array_push($prefs, ...$built['prefs']);
        }
        CourseSelection::assertUniquePrefs($prefs);

        $app->csas_application_no = strtoupper($data['csas_application_no']);
        $app->residence = $data['residence'];
        $app->save();

        $existing = $app->courses()->get()->keyBy('course_id');
        $app->courses()->whereNotIn('course_id', array_column($rows, 'course_id'))->delete();
        foreach ($rows as $row) {
            $app->courses()->updateOrCreate(['course_id' => $row['course_id']], $row + ['statement' => $existing[$row['course_id']]->statement ?? null]);
        }

        // A new course needs its own statement.
        if (array_diff(array_column($rows, 'course_id'), $existing->keys()->all())) {
            $app->invalidate('statements');
        }
    }

    private function saveStatements(Request $request, Application $app): void
    {
        $chosen = $app->courses()->with('course')->get();
        $rules = ['statement_college' => ['required', 'string', 'min:200', 'max:3000']];
        foreach ($chosen as $c) {
            $rules["course_statement.{$c->id}"] = ['required', 'string', 'min:100', 'max:2000'];
        }
        $data = $request->validate($rules, [
            'statement_college.min' => 'Please write at least 200 characters.',
            'course_statement.*.min' => 'Please write at least 100 characters for each course.',
            'course_statement.*.required' => 'Please write a statement for each course.',
        ]);

        $app->statement_college = $data['statement_college'];
        $app->save();
        foreach ($chosen as $c) {
            $c->update(['statement' => $data['course_statement'][$c->id]]);
        }
    }

    private function saveDocuments(Request $request, Application $app): void
    {
        $have = $app->documents()->pluck('type')->all();
        $missing = array_diff($app->requiredDocumentTypes(), $have);
        if ($missing) {
            throw ValidationException::withMessages(['documents' => collect($missing)->map(fn ($t) => 'Please upload: '.DocumentTypes::label($t))->all()]);
        }
        $request->validate(['photo_declaration' => ['accepted']], ['photo_declaration.accepted' => 'Please confirm that your photograph is recent (last 3 months) with a white background.']);
        $app->photo_declared_at ??= now();
        $app->save();
    }

    public function upload(Request $request, string $type)
    {
        $app = $this->app($request);
        abort_if($app->isSubmitted(), 403, 'Your application has already been submitted.');
        abort_unless(in_array($type, $app->requiredDocumentTypes(), true), 404);
        abort_unless($this->canOpen($app, 'documents'), 403);

        $request->validate(['file' => DocumentStore::rules($type)], DocumentStore::messages('file', $type));
        app(DocumentStore::class)->store($app, $request->file('file'), $type);

        return back()->with('status', DocumentTypes::label($type).' uploaded.');
    }

    public function document(Request $request, ApplicationDocument $document)
    {
        abort_unless($document->application->user_id === $request->user()->id, 404);

        return Storage::disk('documents')->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($document->isImage() || $document->mime === 'application/pdf' ? 'inline' : 'attachment').'; filename="'.$document->type.'.'.pathinfo($document->path, PATHINFO_EXTENSION).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function submit(Request $request)
    {
        $app = $this->app($request);
        abort_if($app->isSubmitted(), 403, 'Your application has already been submitted.');

        $request->validate(['declaration' => ['accepted']], ['declaration.accepted' => 'Please accept the declaration to submit.']);

        if ($missing = $this->missingItems($app)) {
            return back()->withErrors(['submit' => $missing]);
        }

        DB::transaction(function () use ($app, $request) {
            $app->refresh();
            $app->status = Application::SUBMITTED;
            $app->submitted_at = now();
            $app->submitted_ip = $request->ip();
            $app->save();
            $app->application_no = config('admissions.application_no_prefix', 'UGADM27').str_pad((string) $app->id, 5, '0', STR_PAD_LEFT);
            $app->save();
        });

        $request->user()->notify(new ApplicationSubmitted($app));

        return redirect()->route('applicant.dashboard')
            ->with('status', "Application {$app->application_no} submitted. Please pay the application fee to complete your application.");
    }
}
