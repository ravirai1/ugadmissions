<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\CallLetter;
use App\Models\SeatOffer;
use App\Services\AllotmentService;
use Illuminate\Http\Request;

/** Applicant: interview call letters and seat offers. */
class InterviewController extends Controller
{
    public function callLetter(Request $request, CallLetter $letter)
    {
        abort_unless($letter->application->user_id === $request->user()->id, 404);

        return view('interview.call-letter', [
            'letters' => collect([$letter->load('application.user', 'application.documents', 'course')]),
            'back' => route('applicant.dashboard'),
            'photoUrl' => fn ($doc) => route('application.document', $doc),
        ]);
    }

    public function offer(Request $request, SeatOffer $offer)
    {
        abort_unless($offer->application->user_id === $request->user()->id, 404);
        app(AllotmentService::class)->lapse();

        return view('interview.offer', ['offer' => $offer->refresh()->load('application.user', 'course', 'choice')]);
    }

    public function respond(Request $request, SeatOffer $offer, AllotmentService $allotment)
    {
        abort_unless($offer->application->user_id === $request->user()->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:accept,decline'], 'confirm' => ['accepted']], ['confirm.accepted' => 'Please tick the box to confirm.']);
        $allotment->lapse();
        $allotment->respond($offer->refresh(), $data['decision'] === 'accept', $request->ip());

        return redirect()->route('applicant.offer', $offer)->with('status', $data['decision'] === 'accept'
            ? 'You have accepted the seat. The College will email you about the next steps.'
            : 'You have declined the seat.');
    }
}
