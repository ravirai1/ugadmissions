<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallLetter;
use App\Models\Course;
use App\Services\InterviewService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Attendance desk (tech team): scan the call letter QR code or search, then mark present or absent. */
class AttendanceController extends Controller
{
    public function __construct(private InterviewService $interviews) {}

    public function index(Request $request)
    {
        $date = $request->date('date') ?? now('Asia/Kolkata')->startOfDay();
        $q = CallLetter::with('application.user', 'course')->orderBy('course_id')->orderBy('id');

        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('letter_no', $term)->orWhereHas('application', fn ($a) => $a->where('application_no', $term)));
        } else {
            $q->whereDate('interview_date', $date->toDateString());
            if ($c = $request->integer('course')) {
                $q->where('course_id', $c);
            }
        }
        $letters = $q->get();

        return view('admin.attendance.index', [
            'letters' => $letters,
            'date' => $date,
            'courses' => Course::orderBy('sort')->pluck('name', 'id'),
            'summary' => ['total' => $letters->count(), 'present' => $letters->where('attendance', CallLetter::PRESENT)->count(), 'absent' => $letters->where('attendance', CallLetter::ABSENT)->count()],
        ]);
    }

    /** Opened from the QR code on the call letter. */
    public function scan(string $token)
    {
        $letter = CallLetter::where('qr_token', $token)->with('application.user', 'application.documents', 'course')->firstOrFail();

        return view('admin.attendance.scan', ['letter' => $letter, 'photo' => $letter->application->documents->firstWhere('type', 'photo')]);
    }

    public function mark(Request $request, CallLetter $letter)
    {
        $data = $request->validate(['attendance' => ['required', Rule::in([CallLetter::PRESENT, CallLetter::ABSENT])]]);
        $this->interviews->markAttendance($letter, $data['attendance'], $request->user('admin'));

        return back()->with('status', "{$letter->letter_no} ({$letter->application->user->name}) marked {$data['attendance']}.");
    }
}
