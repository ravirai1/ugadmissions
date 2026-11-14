<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\SeatOffer;
use App\Models\Setting;
use App\Support\AdmissionWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Admin: open or close applications, and reopen them for chosen vacant courses only. */
class AdmissionWindowController extends Controller
{
    public function edit()
    {
        $held = SeatOffer::whereIn('status', SeatOffer::ACTIVE)->selectRaw('course_id, count(*) as n')->groupBy('course_id')->pluck('n', 'course_id');
        $courses = Course::where('is_active', true)->orderBy('sort')->get()->map(function ($c) use ($held) {
            $seats = collect($c->seats ?? [])->except('PWBD')->sum();

            return ['id' => $c->id, 'name' => $c->name, 'seats' => $seats, 'held' => (int) ($held[$c->id] ?? 0), 'vacant' => max(0, $seats - (int) ($held[$c->id] ?? 0))];
        });

        return view('admin.window.edit', [
            's' => AdmissionWindow::settings(),
            'courses' => $courses,
            'status' => AdmissionWindow::status(),
            'mainOpen' => AdmissionWindow::mainOpen(),
            'reopenActive' => AdmissionWindow::reopenActive(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'main_open' => ['boolean'],
            'main_closes_at' => ['nullable', 'date'],
            'reopen' => ['boolean'],
            'reopen_until' => ['nullable', 'date', 'after:now'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ], ['reopen_until.after' => 'The reopened round must end in the future.']);
        $ist = fn ($v) => $v ? Carbon::parse($v, 'Asia/Kolkata')->utc()->toIso8601String() : null;
        $new = [
            'main_open' => $request->boolean('main_open'),
            'main_closes_at' => $ist($data['main_closes_at'] ?? null),
            'reopen' => $request->boolean('reopen'),
            'reopen_until' => $ist($data['reopen_until'] ?? null),
            'course_ids' => array_values(array_map('intval', $data['course_ids'] ?? [])),
        ];
        if ($new['reopen'] && ! $new['course_ids']) {
            return back()->withErrors(['course_ids' => 'Choose the vacant courses to reopen.'])->withInput();
        }
        $before = AdmissionWindow::settings();
        Setting::put(AdmissionWindow::KEY, $new, $request->user('admin')->id);
        AuditLog::record('settings.admission_window', null, ['before' => $before, 'after' => $new]);

        return back()->with('status', 'Saved. '.AdmissionWindow::status());
    }
}
