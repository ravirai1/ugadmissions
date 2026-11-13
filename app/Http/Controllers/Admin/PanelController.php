<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CallLetter;
use App\Models\Course;
use App\Services\InterviewService;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Interview panel: each member enters marks out of 15 for the candidates of their department's courses. */
class PanelController extends Controller
{
    public function __construct(private InterviewService $interviews) {}

    private function courses(Admin $admin)
    {
        $q = Course::orderBy('sort');
        if (! $admin->hasRole(Roles::ADMIN)) {
            $q->where('department_id', $admin->department_id ?? 0);
        }

        return $q->get();
    }

    public function index(Request $request)
    {
        $admin = $request->user('admin');
        $courses = $this->courses($admin);
        $course = $courses->firstWhere('id', $request->integer('course')) ?? $courses->first();
        $letters = collect();
        $dates = collect();
        $date = null;

        if ($course) {
            $dates = CallLetter::where('course_id', $course->id)->distinct()->orderBy('interview_date')->pluck('interview_date')->map(fn ($d) => $d->toDateString());
            $date = $dates->contains($request->query('date')) ? $request->query('date') : ($dates->first(fn ($d) => $d >= now('Asia/Kolkata')->toDateString()) ?? $dates->last());
            $letters = CallLetter::where('course_id', $course->id)->whereDate('interview_date', $date)
                ->with('application.user', 'marks.admin')->orderBy('id')->get();
        }

        return view('admin.panel.index', [
            'courses' => $courses, 'course' => $course, 'letters' => $letters, 'dates' => $dates, 'date' => $date,
            'me' => $admin, 'max' => InterviewService::maxMarks(),
            'locked' => $course ? InterviewService::isLocked($course->id) : false,
            'seeAll' => $admin->hasRole(Roles::ADMIN),
        ]);
    }

    public function save(Request $request, Course $course)
    {
        $admin = $request->user('admin');
        abort_unless($this->courses($admin)->contains('id', $course->id), 403);
        $max = InterviewService::maxMarks();
        $data = $request->validate([
            'marks' => ['required', 'array'],
            'marks.*' => ['nullable', 'numeric', 'min:0', 'max:'.$max, 'multiple_of:0.5'],
            'remarks' => ['nullable', 'array'],
            'remarks.*' => ['nullable', 'string', 'max:300'],
        ], ['marks.*.max' => "Marks cannot be more than {$max}.", 'marks.*.multiple_of' => 'Use whole or half marks.']);

        $letters = CallLetter::where('course_id', $course->id)->whereIn('id', array_keys($data['marks']))->get()->keyBy('id');
        $saved = 0;
        DB::transaction(function () use ($data, $letters, $admin, &$saved) {
            foreach ($data['marks'] as $id => $value) {
                if ($value === null || $value === '' || ! isset($letters[$id])) {
                    continue;
                }
                $this->interviews->saveMarks($letters[$id], $admin, (float) $value, $data['remarks'][$id] ?? null);
                $saved++;
            }
        });

        return back()->with('status', "Marks saved for {$saved} candidates.");
    }
}
