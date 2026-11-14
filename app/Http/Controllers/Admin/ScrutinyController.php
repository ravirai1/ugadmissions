<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Applicant\ApplicationController as ApplicantForm;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Course;
use App\Models\Department;
use App\Models\Scrutiny;
use App\Models\ScrutinyQuery;
use App\Models\Subject;
use App\Services\Eligibility;
use App\Services\ScrutinyService;
use App\Support\DocumentTypes;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScrutinyController extends Controller
{
    /** Checklist items shown to reviewers. */
    public const CHECKLIST = [
        Scrutiny::ACADEMIC => [
            'class12_marks' => 'Class 12 subjects and marks match the marksheet',
            'cuet_scores' => 'CUET scores match the scorecard',
            'mapping' => 'CUET papers counted match Class 12 subjects and the course rule',
            'csas' => 'DU CSAS preference page matches the preference entered',
            'identity' => 'Name, photo and signature are in order',
        ],
        Scrutiny::RELIGIOUS => [
            'baptism' => 'Baptism certificate (own, or a parent\'s if not baptised) is valid, matches the details and is at least 2 years old',
            'church_letter' => 'Letter from the priest / church membership is valid',
            'denomination' => 'Denomination matches the sub-category (CNI / CNID / Other)',
            'cst' => 'ST certificate checked (CST applicants only)',
        ],
    ];

    public function __construct(private ScrutinyService $service) {}

    public function index(Request $request, string $type)
    {
        $admin = $request->user('admin');
        abort_unless($this->canUseQueue($admin, $type), 403);

        $q = $this->queue($admin, $type)->with(['application.user', 'applicationCourse.course', 'reviewer']);
        if ($s = $request->query('status')) {
            $s === 'reply' ? $q->where('reply_received', true) : $q->where('status', $s);
        } else {
            $q->whereIn('status', [Scrutiny::PENDING, Scrutiny::QUERY]); // to-do list by default
        }
        if ($type === Scrutiny::ACADEMIC && ($course = $request->integer('course'))) {
            $q->whereHas('applicationCourse', fn ($c) => $c->where('course_id', $course));
        }
        if ($type === Scrutiny::ACADEMIC && ($dept = $request->integer('department')) && $admin->hasRole(Roles::ADMIN)) {
            $q->whereHas('applicationCourse.course', fn ($c) => $c->where('department_id', $dept));
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->whereHas('application', fn ($a) => $a->where('application_no', $term)
                ->orWhereHas('user', fn ($u) => $u->where('cuet_no', $term)->orWhere('name', 'like', '%'.addcslashes($term, '%_\\').'%')));
        }

        $counts = $this->queue($admin, $type)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.scrutiny.index', [
            'type' => $type,
            'items' => $q->orderByDesc('reply_received')->orderBy('id')->paginate(30)->withQueryString(),
            'counts' => $counts,
            'replies' => $this->queue($admin, $type)->where('reply_received', true)->count(),
            'courses' => $type === Scrutiny::ACADEMIC ? $this->courses($admin) : collect(),
            'departments' => $admin->hasRole(Roles::ADMIN) ? Department::orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function show(Request $request, Scrutiny $scrutiny, Eligibility $eligibility)
    {
        $admin = $request->user('admin');
        abort_unless($this->canAccess($admin, $scrutiny), 404);

        $app = $scrutiny->application->load(['user', 'class12Marks.class12Subject.subject', 'cuetScores.subject', 'courses.course', 'curState', 'curDistrict', 'permState', 'permDistrict', 'domicileState', 'pwbdType', 'scrutinies.applicationCourse.course', 'scrutinies.reviewer']);
        $data = [
            's' => $scrutiny->load(['queries.attachments', 'queries.raisedBy', 'applicationCourse.course']),
            'app' => $app,
            'user' => $app->user,
            'incomes' => ApplicantForm::INCOME_RANGES,
            'categoryNames' => Category::pluck('name', 'code'),
            'subjects' => Subject::all()->keyBy('id'),
            'uploaded' => $app->documents()->get()->keyBy('type'),
            'required' => $app->requiredDocumentTypes(),
            'checklist' => self::CHECKLIST[$scrutiny->type],
            'docTypes' => collect(DocumentTypes::TYPES)->map(fn ($t) => $t[0])->all(),
            'canDecide' => ! $scrutiny->isDecided() || $admin->hasRole(Roles::ADMIN),
            'isAdmin' => $admin->hasRole(Roles::ADMIN),
        ];

        if ($scrutiny->type === Scrutiny::ACADEMIC) {
            $choice = $scrutiny->applicationCourse;
            $class12Ids = $eligibility->class12SubjectIds($app);
            $scores = $app->cuetScores->pluck('score', 'subject_id')->map(fn ($v) => (float) $v)->all();
            $marksBySubject = $app->class12Marks->filter(fn ($m) => $m->class12Subject->subject_id)->keyBy(fn ($m) => $m->class12Subject->subject_id);

            $data['eval'] = $eligibility->evaluate($choice->course, $app, $scores, $class12Ids);
            $data['mapping'] = collect($choice->papers)->map(fn ($id) => [
                'paper' => $data['subjects'][$id]->name ?? '?',
                'type' => $data['subjects'][$id]->type ?? '',
                'score' => $scores[$id] ?? null,
                'class12' => $marksBySubject[$id] ?? null,
                'needsMatch' => ($data['subjects'][$id]->type ?? '') === Subject::DOMAIN,
            ])->all();
            $data['unmatched'] = $eligibility->unmatchedDomainPapers(array_keys($scores), $class12Ids);
            $data['rules'] = $choice->course->eligibility;
            $data['computedScore'] = $eligibility->scoreFor($choice->papers, $scores);
        }

        return view('admin.scrutiny.show', $data);
    }

    public function decide(Request $request, Scrutiny $scrutiny)
    {
        $admin = $request->user('admin');
        abort_unless($this->canAccess($admin, $scrutiny), 404);
        abort_if($scrutiny->isDecided() && ! $admin->hasRole(Roles::ADMIN), 403, 'This decision is locked. Ask an admin to reopen it.');

        $data = $request->validate([
            'status' => ['required', Rule::in([Scrutiny::APPROVED, Scrutiny::REJECTED])],
            'remarks' => [Rule::requiredIf($request->input('status') === Scrutiny::REJECTED), 'nullable', 'string', 'max:2000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['string', Rule::in(array_keys(self::CHECKLIST[$scrutiny->type]))],
            'verified_cuet_score' => [Rule::requiredIf($scrutiny->type === Scrutiny::ACADEMIC && $request->input('status') === Scrutiny::APPROVED), 'nullable', 'numeric', 'min:0', 'max:1000'],
        ], ['remarks.required' => 'Give the reason for rejection.', 'verified_cuet_score.required' => 'Enter the verified CUET total.']);

        if ($scrutiny->queries()->where('status', ScrutinyQuery::OPEN)->exists()) {
            return back()->withErrors(['status' => 'Close the open query before deciding.']);
        }

        $this->service->decide($scrutiny, $data, $admin);

        return redirect()->route('admin.scrutiny.index', $scrutiny->type)
            ->with('status', $scrutiny->application->application_no.': '.$scrutiny->statusLabel().'.');
    }

    public function query(Request $request, Scrutiny $scrutiny)
    {
        $admin = $request->user('admin');
        abort_unless($this->canAccess($admin, $scrutiny), 404);
        abort_if($scrutiny->isDecided() && ! $admin->hasRole(Roles::ADMIN), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'document_types' => ['nullable', 'array'],
            'document_types.*' => [Rule::in(array_keys(DocumentTypes::TYPES))],
        ]);
        $this->service->raiseQuery($scrutiny, $data['message'], $data['document_types'] ?? [], $admin);

        return back()->with('status', 'Query sent to '.$scrutiny->application->user->email.'.');
    }

    public function closeQuery(Request $request, ScrutinyQuery $query)
    {
        abort_unless($this->canAccess($request->user('admin'), $query->scrutiny), 404);
        $this->service->close($query, $request->user('admin'));

        return back()->with('status', 'Query closed.');
    }

    public function reopen(Scrutiny $scrutiny)
    {
        $this->service->reopen($scrutiny);

        return back()->with('status', 'Reopened for review.');
    }

    /* ---- access ---- */

    private function canUseQueue(Admin $admin, string $type): bool
    {
        return $admin->hasRole(Roles::ADMIN)
            || ($type === Scrutiny::ACADEMIC && $admin->role === Roles::DEPARTMENT)
            || ($type === Scrutiny::RELIGIOUS && $admin->role === Roles::RELIGIOUS);
    }

    private function canAccess(Admin $admin, Scrutiny $s): bool
    {
        if (! $this->canUseQueue($admin, $s->type)) {
            return false;
        }

        return $this->queue($admin, $s->type)->whereKey($s->id)->exists();
    }

    private function queue(Admin $admin, string $type): Builder
    {
        $q = Scrutiny::query()->where('type', $type)->whereHas('application', fn ($a) => $a->whereNotNull('fee_paid_at'));
        if ($type === Scrutiny::ACADEMIC && ! $admin->hasRole(Roles::ADMIN)) {
            $q->whereHas('applicationCourse.course', fn ($c) => $c->where('department_id', $admin->department_id ?? 0));
        }

        return $q;
    }

    private function courses(Admin $admin)
    {
        return Course::orderBy('sort')
            ->when(! $admin->hasRole(Roles::ADMIN), fn ($q) => $q->where('department_id', $admin->department_id ?? 0))
            ->pluck('name', 'id');
    }
}
