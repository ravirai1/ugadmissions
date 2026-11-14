<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Course;
use App\Models\SeatOffer;
use App\Services\AllotmentService;
use App\Services\InterviewService;
use App\Services\MeritService;
use App\Support\Excel;
use Illuminate\Http\Request;

/** Merit lists course-wise and category-wise (CUET 85 + interview 15 = 100), and the highest merit across courses. */
class MeritController extends Controller
{
    public function __construct(private MeritService $merit, private AllotmentService $allotment) {}

    public function index()
    {
        $courses = Course::orderBy('sort')->get();

        return view('admin.merit.index', [
            'courses' => $courses,
            'lists' => $courses->mapWithKeys(fn ($c) => [$c->id => $this->merit->forCourse($c)]),
            'highest' => $this->merit->highest(),
            'locked' => InterviewService::lockedCourses(),
        ]);
    }

    public function show(Request $request, Course $course)
    {
        $list = $this->merit->forCourse($course);
        $category = $request->query('category');
        $status = $this->statuses($course, $list['ranked']);

        return view('admin.merit.show', [
            'course' => $course,
            'list' => $list,
            'rows' => $category ? $list['ranked']->filter(fn ($r) => AllotmentService::fits($r, $category))->values() : $list['ranked'],
            'category' => $category,
            'categories' => collect(AllotmentService::seats($course))->keys()->merge($list['ranked']->pluck('category'))->unique()->values(),
            'categoryNames' => Category::pluck('name', 'code'),
            'seats' => AllotmentService::seats($course),
            'status' => $status,
            'locked' => InterviewService::isLocked($course->id),
        ]);
    }

    public function export(Course $course)
    {
        $ranked = $this->merit->forCourse($course)['ranked'];
        $status = $this->statuses($course, $ranked);
        AuditLog::record('merit.export', $course);

        return Excel::download('merit-'.$course->code.'.xlsx', ['Merit list' => [
            'headers' => ['Rank', 'Category rank', 'Application no', 'Name', 'Category', 'CST', 'PwBD', 'DU preference', 'CUET verified', 'CUET max', 'CUET (85)', 'Interview (15)', 'Panel members', 'Total (100)', 'Class 12 %', 'Date of birth', 'Status'],
            'rows' => $ranked->map(fn ($r) => [
                $r['rank'], $r['cat_rank'], $r['app']->application_no, $r['app']->user->name, $r['category'], $r['cst'] ? 'Yes' : 'No', $r['pwbd'] ? 'Yes' : 'No', $r['choice']->du_preference,
                $r['cuet_raw'], $r['cuet_max'], $r['cuet'], $r['interview'], $r['panel'], $r['total'], $r['class12'], $r['app']->dob?->format('d-m-Y'),
                $status[$r['app']->id]['label'] ?? '',
            ]),
        ]]);
    }

    /**
     * Status of each ranked candidate: their offer (if any), or what the next allotment round would do.
     *
     * @return array<int, array{label: string, kind: string}>
     */
    private function statuses(Course $course, $ranked): array
    {
        $offers = SeatOffer::whereIn('application_id', $ranked->map(fn ($r) => $r['app']->id))->with('course', 'choice')->latest('id')->get()->unique('application_id')->keyBy('application_id');
        $preview = InterviewService::isLocked($course->id) ? $this->allotment->preview()['assigned'] : [];
        $out = [];
        foreach ($ranked as $r) {
            $id = $r['app']->id;
            $myPref = $r['choice']->du_preference;
            if (($a = $preview[$id] ?? null) && $a['course_id'] === $course->id && $a['from']) {
                $out[$id] = ['kind' => 'next', 'label' => 'Moves up here in next round ('.$a['bucket'].')'];
            } elseif ($o = $offers[$id] ?? null) {
                $out[$id] = match (true) {
                    $o->course_id === $course->id => ['kind' => $o->status, 'label' => SeatOffer::STATUSES[$o->status].' ('.$o->seat_category.', round '.$o->round.')'],
                    in_array($o->status, SeatOffer::ACTIVE, true) && $o->choice->du_preference > $myPref => ['kind' => 'waiting', 'label' => 'Waiting (holds a seat in '.$o->course->name.', can move up)'],
                    default => ['kind' => 'elsewhere', 'label' => 'Already selected – first preference: '.$o->course->name],
                };
                if ($o->course_id !== $course->id && ! in_array($o->status, SeatOffer::ACTIVE, true)) {
                    $out[$id] = ['kind' => $o->status, 'label' => SeatOffer::STATUSES[$o->status].' in '.$o->course->name];
                }
            } elseif ($a = $preview[$id] ?? null) {
                $other = $a['course_id'] !== $course->id;
                $out[$id] = ! $other
                    ? ['kind' => 'next', 'label' => 'In next round ('.$a['bucket'].')']
                    : ['kind' => 'elsewhere', 'label' => 'Already selected – first preference: '.Course::find($a['course_id'])->name];
                if ($other && $a['row']['choice']->du_preference > $myPref) {
                    $out[$id] = ['kind' => 'waiting', 'label' => 'Waiting'];
                }
            }
        }

        return $out;
    }
}
