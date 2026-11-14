<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Course;
use App\Models\SeatOffer;
use App\Models\Setting;
use App\Services\AllotmentService;
use Illuminate\Http\Request;

/** Seat matrix: seats per course and category, plus PwBD supernumerary seats. */
class SeatController extends Controller
{
    /**
     * Seat columns: every category except the Christian parent (General first), then CST.
     * CST seats go to Christian applicants who declared CST (Christian Scheduled Tribe), whatever their denomination.
     */
    public static function categories()
    {
        return Category::where('is_active', true)->where('code', '!=', Category::CHRISTIAN)
            ->orderByRaw('case when parent_code is null then 0 else 1 end')->orderBy('sort')->orderBy('name')->get(['code', 'name'])
            ->push(new Category(['code' => 'CST', 'name' => 'CST – Christian Scheduled Tribe']));
    }

    public function index()
    {
        return view('admin.seats.index', [
            'courses' => Course::orderBy('sort')->get(),
            'categories' => self::categories(),
            'percent' => AllotmentService::pwbdPercent(),
            'offers' => SeatOffer::groupBy('course_id')->selectRaw('course_id, count(*) as n')->pluck('n', 'course_id'),
        ]);
    }

    public function update(Request $request)
    {
        $codes = self::categories()->pluck('code')->all();
        $data = $request->validate([
            'pwbd_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'seats' => ['array'],
            'seats.*' => ['array'],
            'seats.*.*' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], ['seats.*.*.integer' => 'Seats must be whole numbers.', 'seats.*.*.min' => 'Seats cannot be negative.']);

        foreach (Course::all() as $course) {
            $row = $data['seats'][$course->id] ?? [];
            $seats = [];
            foreach ($codes as $code) {
                if (($n = (int) ($row[$code] ?? 0)) > 0) {
                    $seats[$code] = $n;
                }
            }
            $pwbd = $row['PWBD'] ?? null;
            if ($pwbd !== null && $pwbd !== '') {
                $seats['PWBD'] = (int) $pwbd; // a number set here overrides the percentage, including 0
            }
            if (($course->seats ?? []) != $seats) {
                AuditLog::record('seats.update', $course, ['from' => $course->seats, 'to' => $seats]);
                $course->update(['seats' => $seats ?: null]);
            }
        }
        Setting::put('seats.pwbd_percent', (float) $data['pwbd_percent'], $request->user('admin')->id);

        return back()->with('status', 'Seat matrix saved.');
    }
}
