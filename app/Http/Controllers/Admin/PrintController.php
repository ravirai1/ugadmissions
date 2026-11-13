<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Applicant\ApplicationController as ApplicantForm;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Application;
use App\Models\Category;
use App\Models\Course;
use App\Models\PrintLog;
use App\Models\Subject;
use App\Support\ApplicationAccess;
use Illuminate\Http\Request;

/**
 * Print desk: application forms (one or a whole course), receipts and documents. Every print is logged.
 */
class PrintController extends Controller
{
    /** Most forms printed in one bulk run, so the browser can cope. */
    public const BULK_LIMIT = 200;

    private const RELATIONS = ['user', 'class12Marks.class12Subject', 'cuetScores.subject', 'courses.course.department', 'curState', 'curDistrict', 'permState', 'permDistrict', 'domicileState', 'pwbdType', 'payments', 'documents'];

    public function index(Request $request)
    {
        $results = collect();
        if ($term = trim((string) $request->query('q'))) {
            $results = ApplicationAccess::scope($request->user('admin'), Application::query())->with('user', 'payments')
                ->where(fn ($w) => $w->where('application_no', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('cuet_no', $term)->orWhere('email', $term)->orWhere('mobile', $term)))
                ->limit(20)->get();
        }

        return view('admin.print.index', [
            'results' => $results,
            'courses' => Course::orderBy('name')->pluck('name', 'id'),
            'recent' => PrintLog::with('admin', 'application')->latest('id')->limit(10)->get(),
        ]);
    }

    public function form(Request $request, Application $application)
    {
        abort_unless(ApplicationAccess::canView($request->user('admin'), $application), 404);
        PrintLog::record('application', $application);

        return view('admin.print.forms', $this->shared() + ['apps' => collect([$application->load(self::RELATIONS)]), 'title' => $application->application_no]);
    }

    /** All fee-paid forms for a course, in application-number order, one per page. */
    public function bulk(Request $request)
    {
        $data = $request->validate(['course_id' => ['required', 'integer', 'exists:courses,id'], 'offset' => ['nullable', 'integer', 'min:0']]);
        $offset = $data['offset'] ?? 0;
        $base = ApplicationAccess::scope($request->user('admin'), Application::query())->whereNotNull('fee_paid_at')
            ->whereHas('courses', fn ($c) => $c->where('course_id', $data['course_id'])->where('payment_status', 'paid'));
        $total = (clone $base)->count();
        $apps = $base->with(self::RELATIONS)->orderBy('application_no')->skip($offset)->take(self::BULK_LIMIT)->get();
        $course = Course::find($data['course_id']);

        PrintLog::record('application-bulk', null, ['course' => $course->name, 'from' => $apps->first()?->application_no, 'to' => $apps->last()?->application_no, 'count' => $apps->count()]);

        return view('admin.print.forms', $this->shared() + [
            'apps' => $apps, 'title' => $course->name, 'total' => $total, 'offset' => $offset,
            'next' => $offset + self::BULK_LIMIT < $total ? route('admin.print.bulk', ['course_id' => $course->id, 'offset' => $offset + self::BULK_LIMIT]) : null,
        ]);
    }

    public function log(Request $request)
    {
        $q = PrintLog::with('admin', 'application')->latest('id');
        if ($item = $request->query('item')) {
            $q->where('item', $item);
        }
        if ($by = $request->integer('admin')) {
            $q->where('admin_id', $by);
        }
        if ($no = trim((string) $request->query('q'))) {
            $q->whereHas('application', fn ($a) => $a->where('application_no', $no));
        }

        return view('admin.print.log', [
            'logs' => $q->paginate(50)->withQueryString(),
            'staff' => Admin::whereIn('id', PrintLog::select('admin_id')->distinct())->orderBy('name')->pluck('name', 'id'),
            'items' => PrintLog::ITEMS,
        ]);
    }

    private function shared(): array
    {
        return [
            'incomes' => ApplicantForm::INCOME_RANGES,
            'categoryNames' => Category::pluck('name', 'code'),
            'subjects' => Subject::all()->keyBy('id'),
            'printedBy' => auth('admin')->user()->name,
        ];
    }
}
