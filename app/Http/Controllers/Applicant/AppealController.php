<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Scrutiny;
use App\Services\AppealService;
use App\Services\DocumentStore;
use Illuminate\Http\Request;
use RuntimeException;

/** An applicant appeals once against a scrutiny rejection, within the appeal period. */
class AppealController extends Controller
{
    public function create(Request $request, Scrutiny $scrutiny)
    {
        $this->own($request, $scrutiny);
        if ($scrutiny->appeal) {
            return view('applicant.appeal', ['s' => $scrutiny, 'appeal' => $scrutiny->appeal->load('attachments')]);
        }
        abort_unless($scrutiny->canAppeal(), 403, 'This decision can no longer be appealed.');

        return view('applicant.appeal', ['s' => $scrutiny, 'appeal' => null]);
    }

    public function store(Request $request, Scrutiny $scrutiny, AppealService $appeals)
    {
        $this->own($request, $scrutiny);
        abort_unless($scrutiny->canAppeal(), 403, 'This decision can no longer be appealed.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:50', 'max:3000'],
            'files' => ['nullable', 'array', 'max:3'],
            'files.*' => DocumentStore::rules('other'),
            'declaration' => ['accepted'],
        ], [
            'reason.min' => 'Explain in at least 50 characters why the decision should be reviewed.',
            'files.max' => 'You can attach up to 3 documents.',
            'declaration.accepted' => 'Please tick the declaration.',
        ] + DocumentStore::messages('files.*', 'other'));

        try {
            $appeals->submit($scrutiny, $data['reason'], $request->file('files', []), $request->ip());
        } catch (RuntimeException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect()->route('applicant.dashboard')->with('status', 'Your appeal has been sent to the College. You will be emailed when it is decided.');
    }

    private function own(Request $request, Scrutiny $scrutiny): void
    {
        abort_unless($scrutiny->application->user_id === $request->user()->id, 404);
    }
}
