<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Applicant\ApplicationController as ApplicantForm;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Course;
use App\Models\PrintLog;
use App\Models\State;
use App\Models\Subject;
use App\Support\ApplicationAccess;
use App\Support\Excel;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user('admin');

        return view('admin.applications.index', [
            'apps' => $this->filtered($request)->with(['user', 'courses.course'])->latest('submitted_at')->paginate(25)->withQueryString(),
            'courses' => $this->visibleCourses($request),
            'categories' => Category::whereNull('parent_code')->orderBy('sort')->pluck('name', 'code'),
            'subcategories' => Category::where('parent_code', Category::CHRISTIAN)->orderBy('sort')->pluck('name', 'code'),
            'states' => State::orderBy('name')->pluck('name', 'id'),
            'genders' => ApplicantForm::GENDERS,
        ]);
    }

    public function show(Request $request, Application $application)
    {
        $admin = $request->user('admin');
        abort_unless(ApplicationAccess::canView($admin, $application), 404);
        // the religious team works only from the religious scrutiny page (basic and religious details)
        if ($admin->role === Roles::RELIGIOUS) {
            $rel = $application->scrutinies()->where('type', 'religious')->first();
            abort_unless($rel, 404);

            return redirect()->route('admin.scrutiny.show', $rel);
        }
        $application->load(['user', 'class12Marks.class12Subject', 'cuetScores.subject', 'courses.course.department', 'curState', 'curDistrict', 'permState', 'permDistrict', 'domicileState', 'pwbdType', 'payments']);
        AuditLog::record('application.view', $application);

        return view('admin.applications.show', [
            'app' => $application,
            'user' => $application->user,
            'incomes' => ApplicantForm::INCOME_RANGES,
            'categoryNames' => Category::pluck('name', 'code'),
            'subjects' => Subject::all()->keyBy('id'),
            'uploaded' => $application->documents()->get()->keyBy('type'),
            'required' => $application->requiredDocumentTypes(),
        ]);
    }

    public function document(Request $request, ApplicationDocument $document)
    {
        abort_unless(ApplicationAccess::canView($request->user('admin'), $document->application), 404);
        abort_if($request->user('admin')->role === Roles::RELIGIOUS && ! in_array($document->type, ScrutinyController::RELIGIOUS_DOCS, true), 404);
        if ($request->boolean('print')) {
            PrintLog::record('document', $document->application, ['type' => $document->type]);
        }

        return Storage::disk('documents')->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="'.$document->application->application_no.'-'.$document->type.'.'.pathinfo($document->path, PATHINFO_EXTENSION).'"',
            'Cache-Control' => 'private, no-store',
            'X-Frame-Options' => 'SAMEORIGIN', // shown beside the data on the scrutiny page
        ]);
    }

    public function export(Request $request)
    {
        $apps = $this->filtered($request)->with(['user', 'courses.course', 'curState', 'permState', 'domicileState', 'pwbdType'])->orderBy('application_no')->get();
        $names = Category::pluck('name', 'code');
        AuditLog::record('application.export', null, ['count' => $apps->count(), 'filters' => $request->except('page')]);

        $rows = $apps->map(fn (Application $a) => [
            $a->application_no, $a->user->name, $a->user->cuet_no, $a->user->email, $a->user->mobile, $a->gender, $a->dob?->format('d-m-Y'),
            $names[$a->category_code] ?? $a->category_code, $names[$a->christian_subcategory] ?? '', $a->is_cst ? 'Yes' : 'No',
            $a->is_pwbd ? ($a->pwbdType?->name.' ('.$a->pwbd_percentage.'%)') : 'No', $a->residence ? 'Yes' : 'No',
            $a->curState?->name, $a->permState?->name ?? ($a->perm_same ? $a->curState?->name : ''), $a->domicileState?->name,
            (float) $a->class12Percentage(), $a->csas_application_no,
            $a->courses->sortBy('du_preference')->map(fn ($c) => $c->course->name.' (DU '.$c->du_preference.($c->jmc_preference ? ', JMC '.$c->jmc_preference : '').', CUET '.$c->cuet_score.')'.($c->payment_status === 'paid' ? '' : ' [unpaid]'))->implode('; '),
            $a->isPaid() ? 'Paid' : 'Unpaid', $a->submitted_at?->timezone('Asia/Kolkata')->format('d-m-Y H:i'),
        ]);

        return Excel::download('applications-'.now('Asia/Kolkata')->format('Ymd-Hi').'.xlsx', ['Applications' => [
            'headers' => ['Application no', 'Name', 'CUET no', 'Email', 'Mobile', 'Gender', 'DOB', 'Category', 'Christian sub-category', 'CST', 'PwBD', 'Residence',
                'Current state', 'Permanent state', 'State belongs to', 'Class 12 %', 'DU CSAS no', 'Courses (preferences, CUET total)', 'Fee', 'Submitted (IST)'],
            'rows' => $rows,
        ]]);
    }

    private function visibleCourses(Request $request)
    {
        $admin = $request->user('admin');
        $q = Course::orderBy('sort');
        if (in_array($admin->role, StaffController::DEPARTMENT_ROLES, true)) {
            $q->where('department_id', $admin->department_id ?? 0);
        }

        return $q->pluck('name', 'id');
    }

    private function filtered(Request $request): Builder
    {
        $q = ApplicationAccess::scope($request->user('admin'), Application::query());

        match ($request->query('fee')) {
            'paid' => $q->whereNotNull('fee_paid_at'),
            'unpaid' => $q->whereNull('fee_paid_at'),
            default => null,
        };
        if ($course = $request->integer('course')) {
            $q->whereHas('courses', fn ($c) => $c->where('course_id', $course));
        }
        foreach (['category' => 'category_code', 'subcategory' => 'christian_subcategory', 'gender' => 'gender'] as $param => $col) {
            if ($v = $request->query($param)) {
                $q->where($col, $v);
            }
        }
        foreach (['state' => 'cur_state_id', 'perm_state' => 'perm_state_id', 'domicile' => 'domicile_state_id'] as $param => $col) {
            if ($v = $request->integer($param)) {
                $q->where($col, $v);
            }
        }
        if ($request->boolean('residence')) {
            $q->where('residence', true);
        }
        if ($request->boolean('pwbd')) {
            $q->where('is_pwbd', true);
        }
        if ($request->boolean('cst')) {
            $q->where('is_cst', true);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('application_no', $term)->orWhere('csas_application_no', $term)
                ->orWhereHas('user', fn ($u) => $u->where('email', $term)->orWhere('cuet_no', $term)->orWhere('mobile', $term)
                    ->orWhere('name', 'like', '%'.addcslashes($term, '%_\\').'%')));
        }

        return $q;
    }
}
