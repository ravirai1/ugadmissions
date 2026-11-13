<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
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

        return view('applicant.dashboard', [
            'notices' => \App\Models\Notice::forUser($request->user())->limit(5)->get(),
            'user' => $user,
            'app' => $app,
            'stages' => $stages,
            'currentStage' => $app?->isPaid() ? 3 : ($app?->isSubmitted() ? 2 : 1),
            'payments' => $app ? $app->payments()->get() : collect(),
            'openQueries' => $app ? $app->queries()->where('status', 'open')->get() : collect(),
            'scrutinies' => $app ? $app->scrutinies()->with('applicationCourse.course')->get() : collect(),
            'progress' => (int) round(min($done, $total) / $total * 100),
        ]);
    }
}
