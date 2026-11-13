<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\SeatOffer;
use App\Services\AllotmentService;
use App\Support\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Seat allotment rounds, offers and the selected-list dashboard. */
class AllotmentController extends Controller
{
    public function __construct(private AllotmentService $allotment) {}

    public function index()
    {
        $this->allotment->lapse();
        $preview = $this->allotment->preview();

        return view('admin.allotment.index', [
            'preview' => $preview,
            'byCourse' => collect($preview['assigned'])->groupBy('course_id'),
            'rounds' => SeatOffer::selectRaw('round, count(*) as n, min(deadline_at) as deadline, max(created_at) as published')->groupBy('round')->orderByDesc('round')->get(),
            'notLocked' => Course::whereNotIn('id', $preview['courses']->keys())->orderBy('sort')->pluck('name'),
            'defaultDeadline' => now('Asia/Kolkata')->addDays((int) config('admissions.merit.offer_days', 3))->setTime(17, 0),
        ]);
    }

    public function publish(Request $request)
    {
        $data = $request->validate(['deadline' => ['required', 'date']]);
        $offers = $this->allotment->publish(Carbon::parse($data['deadline'], 'Asia/Kolkata')->utc(), $request->user('admin'));

        return redirect()->route('admin.allotment.selections')->with('status', "Round {$offers->first()->round} published: {$offers->count()} seats offered and emailed.");
    }

    public function lapse()
    {
        $n = $this->allotment->lapse();

        return back()->with('status', "{$n} offers lapsed.");
    }

    /** Selected list: course-wise and category-wise counts, plus the full list. */
    public function selections(Request $request)
    {
        $this->allotment->lapse();
        $courses = Course::orderBy('sort')->get();
        $offers = SeatOffer::with('application.user', 'course')->orderBy('course_id')->orderBy('merit_rank');
        if ($s = $request->query('status')) {
            $offers->where('status', $s);
        }
        if ($c = $request->integer('course')) {
            $offers->where('course_id', $c);
        }
        $all = SeatOffer::get(['course_id', 'seat_category', 'status']);
        $cats = $courses->flatMap(fn ($c) => array_keys(AllotmentService::seats($c)))->merge($all->pluck('seat_category'))->unique()->values();

        $matrix = $courses->mapWithKeys(fn ($c) => [$c->id => [
            'seats' => AllotmentService::seats($c),
            'accepted' => $all->where('course_id', $c->id)->where('status', SeatOffer::ACCEPTED)->countBy('seat_category')->all(),
            'offered' => $all->where('course_id', $c->id)->where('status', SeatOffer::OFFERED)->countBy('seat_category')->all(),
        ]]);

        return view('admin.allotment.selections', [
            'courses' => $courses, 'cats' => $cats, 'matrix' => $matrix,
            'offers' => $offers->paginate(50)->withQueryString(),
            'totals' => $all->countBy('status')->all(),
            'categoryNames' => Category::pluck('name', 'code'),
        ]);
    }

    public function export()
    {
        $ist = fn ($d) => $d?->timezone('Asia/Kolkata')->format('d-m-Y H:i');
        $courses = Course::orderBy('sort')->get();
        $all = SeatOffer::get(['course_id', 'seat_category', 'status']);
        $cats = $courses->flatMap(fn ($c) => array_keys(AllotmentService::seats($c)))->merge($all->pluck('seat_category'))->unique()->values();

        return Excel::download('selected-list.xlsx', [
            'Category-wise' => [
                'headers' => array_merge(['Course'], $cats->flatMap(fn ($c) => ["$c seats", "$c accepted", "$c offered"])->all(), ['Total accepted', 'Total offered']),
                'rows' => $courses->map(function ($c) use ($all, $cats) {
                    $seats = AllotmentService::seats($c);
                    $mine = $all->where('course_id', $c->id);
                    $row = [$c->name];
                    foreach ($cats as $cat) {
                        array_push($row, $seats[$cat] ?? 0, $mine->where('seat_category', $cat)->where('status', SeatOffer::ACCEPTED)->count(), $mine->where('seat_category', $cat)->where('status', SeatOffer::OFFERED)->count());
                    }

                    return array_merge($row, [$mine->where('status', SeatOffer::ACCEPTED)->count(), $mine->where('status', SeatOffer::OFFERED)->count()]);
                }),
            ],
            'Offers' => [
                'headers' => ['Offer no', 'Round', 'Course', 'Seat category', 'Merit rank', 'Application no', 'Name', 'Email', 'Mobile', 'CUET (85)', 'Interview (15)', 'Total (100)', 'Status', 'Deadline (IST)', 'Responded (IST)'],
                'rows' => SeatOffer::with('application.user', 'course')->orderBy('course_id')->orderBy('merit_rank')->lazy()->map(fn ($o) => [
                    $o->offer_no, $o->round, $o->course->name, $o->seat_category, $o->merit_rank, $o->application->application_no, $o->application->user->name,
                    $o->application->user->email, $o->application->user->mobile, (float) $o->cuet_part, (float) $o->interview_part, (float) $o->total,
                    SeatOffer::STATUSES[$o->status], $ist($o->deadline_at), $ist($o->responded_at),
                ]),
            ],
        ]);
    }
}
