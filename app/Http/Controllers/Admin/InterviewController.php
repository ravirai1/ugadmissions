<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CallLetter;
use App\Models\Course;
use App\Services\InterviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Admin: issue interview call letters course-wise and finalise marks. */
class InterviewController extends Controller
{
    public function __construct(private InterviewService $interviews) {}

    public function index()
    {
        $counts = CallLetter::selectRaw("course_id, count(*) as issued, sum(case when attendance = 'present' then 1 else 0 end) as present, sum(case when attendance = 'absent' then 1 else 0 end) as absent")
            ->groupBy('course_id')->get()->keyBy('course_id');

        return view('admin.interviews.index', [
            'courses' => Course::orderBy('sort')->get(),
            'counts' => $counts,
            'eligible' => Course::orderBy('sort')->get()->mapWithKeys(fn ($c) => [$c->id => $this->interviews->eligible($c)->count()]),
            'locked' => InterviewService::lockedCourses(),
        ]);
    }

    /** Attendance summary: present, absent and not marked, by date and course, with marks progress. */
    public function summary()
    {
        $rows = CallLetter::query()
            ->selectRaw("interview_date, course_id, count(*) as issued,
                sum(case when attendance = 'present' then 1 else 0 end) as present,
                sum(case when attendance = 'absent' then 1 else 0 end) as absent,
                sum(case when attendance is null then 1 else 0 end) as unmarked,
                sum(case when exists (select 1 from interview_marks m where m.call_letter_id = call_letters.id) then 1 else 0 end) as marked")
            ->groupBy('interview_date', 'course_id')->orderBy('interview_date')->orderBy('course_id')->get();
        $courses = Course::pluck('name', 'id');
        $sum = fn ($c, $k) => (int) $c->sum($k);
        $totals = ['issued' => $sum($rows, 'issued'), 'present' => $sum($rows, 'present'), 'absent' => $sum($rows, 'absent'), 'unmarked' => $sum($rows, 'unmarked'), 'marked' => $sum($rows, 'marked')];

        return view('admin.interviews.summary', [
            'byDate' => $rows->groupBy(fn ($r) => Carbon::parse($r->interview_date)->toDateString()),
            'byCourse' => $rows->groupBy('course_id')->map(fn ($g) => ['issued' => $sum($g, 'issued'), 'present' => $sum($g, 'present'), 'absent' => $sum($g, 'absent'), 'unmarked' => $sum($g, 'unmarked'), 'marked' => $sum($g, 'marked')]),
            'courses' => $courses,
            'totals' => $totals,
        ]);
    }

    public function course(Course $course)
    {
        $letters = CallLetter::where('course_id', $course->id)->with('application.user', 'marks')->orderBy('interview_date')->orderBy('id')->get();
        $issued = $letters->pluck('application_course_id')->all();

        return view('admin.interviews.course', [
            'course' => $course,
            'letters' => $letters,
            'pending' => $this->interviews->eligible($course)->reject(fn ($c) => in_array($c->id, $issued, true))->sortBy(fn ($c) => $c->application->application_no)->values(),
            'locked' => InterviewService::isLocked($course->id),
            'venue' => config('admissions.merit.interview_venue'),
        ]);
    }

    public function issue(Request $request, Course $course)
    {
        $data = $request->validate([
            'interview_date' => ['required', 'date', 'after_or_equal:today'],
            'reporting_time' => ['required', 'string', 'max:20'],
            'venue' => ['required', 'string', 'max:250'],
            'choices' => ['required', 'array', 'min:1'],
            'choices.*' => ['integer'],
        ], ['choices.required' => 'Tick at least one candidate.']);

        $choices = $this->interviews->eligible($course)->whereIn('id', array_map('intval', $data['choices']));
        $letters = $this->interviews->issue($course, $choices, $data['interview_date'], $data['reporting_time'], $data['venue'], $request->user('admin'), $request->boolean('email', true));

        return back()->with('status', "{$letters->count()} call letters issued".($request->boolean('email', true) ? ' and emailed.' : '.'));
    }

    public function cancel(CallLetter $letter)
    {
        abort_if($letter->attendance !== null || InterviewService::isLocked($letter->course_id), 422, 'Attendance is already marked for this call letter.');
        AuditLog::record('interview.cancel', $letter, ['letter_no' => $letter->letter_no]);
        $letter->delete();

        return back()->with('status', 'Call letter cancelled.');
    }

    public function lock(Request $request, Course $course)
    {
        $lock = $request->boolean('lock');
        $this->interviews->setLocked($course, $lock, $request->user('admin'));

        return back()->with('status', $lock ? 'Marks finalised. The course can now go into seat allotment.' : 'Marks unlocked for changes.');
    }
}
