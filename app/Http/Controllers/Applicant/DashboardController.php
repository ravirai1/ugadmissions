<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\CallLetter;
use App\Models\Notice;
use App\Models\ScrutinyQuery;
use App\Models\SeatOffer;
use App\Services\AllotmentService;
use App\Support\ApplicationSteps;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $app = $user->application()->with('courses.course')->first();
        $stages = ['Registered', 'Application Submitted', 'Fee Paid', 'Scrutiny', 'Interview', 'Result'];

        $done = $app ? count(array_intersect($app->completed_steps ?? [], ApplicationSteps::slugs())) : 0;
        $total = count(ApplicationSteps::slugs()) - 1;

        if ($app) {
            app(AllotmentService::class)->lapse();
        }
        $letters = $app ? CallLetter::where('application_id', $app->id)->with('course')->orderBy('interview_date')->get() : collect();
        $offers = $app ? SeatOffer::where('application_id', $app->id)->with('course')->latest('id')->get() : collect();

        $queries = $app ? $app->queries()->with('attachments', 'scrutiny.applicationCourse.course')->get() : collect();

        return view('applicant.dashboard', [
            'callLetters' => $letters,
            'offers' => $offers,
            'activeOffer' => $offers->first(fn ($o) => in_array($o->status, SeatOffer::ACTIVE, true)),
            'notices' => Notice::forUser($request->user())->limit(5)->get(),
            'user' => $user,
            'app' => $app,
            'stages' => $stages,
            'currentStage' => $offers->isNotEmpty() ? 5 : ($letters->isNotEmpty() ? 4 : ($app?->isPaid() ? 3 : ($app?->isSubmitted() ? 2 : 1))),
            'payments' => $app ? $app->payments()->with('events')->get() : collect(),
            'queries' => $queries,
            'openQueries' => $queries->where('status', ScrutinyQuery::OPEN),
            'scrutinies' => $app ? $app->scrutinies()->with('applicationCourse.course')->get() : collect(),
            'progress' => (int) round(min($done, $total) / $total * 100),
        ]);
    }
}
