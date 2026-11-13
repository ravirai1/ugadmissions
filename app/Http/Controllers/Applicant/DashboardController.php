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
            'user' => $user,
            'app' => $app,
            'stages' => $stages,
            'currentStage' => $app?->isSubmitted() ? 2 : 1,
            'progress' => (int) round(min($done, $total) / $total * 100),
        ]);
    }
}
