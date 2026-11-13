<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Status tracker; later steps fill in real values from the application.
        $stages = ['Registered', 'Application Submitted', 'Fee Paid', 'Scrutiny', 'Interview', 'Result'];

        return view('applicant.dashboard', [
            'user' => $request->user(),
            'stages' => $stages,
            'currentStage' => 0,
        ]);
    }
}
