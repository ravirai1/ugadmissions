<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Rules\ValidCaptcha;
use App\Services\Helpdesk;
use App\Services\MathCaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Applicant side of the helpdesk: chat for logged-in applicants, and a query form for anyone.
 */
class HelpController extends Controller
{
    public function __construct(private Helpdesk $helpdesk) {}

    public function index(Request $request)
    {
        return $request->user()?->hasVerifiedEmail() ? redirect()->route('help.chat') : redirect()->route('help.query');
    }

    public function queryForm(Request $request, MathCaptcha $captcha)
    {
        return view('help.query', ['captcha' => $captcha->generate('help'), 'user' => $request->user()]);
    }

    public function submitQuery(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'mobile' => ['nullable', 'digits:10'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'attachment' => Helpdesk::attachmentRules(),
            'captcha' => ['required', new ValidCaptcha('help')],
        ], ['attachment.mimes' => 'The screenshot must be a JPG or PNG image.', 'attachment.mimetypes' => 'The screenshot must be a JPG or PNG image.']);

        $ticket = $this->helpdesk->openQuery($data + ['user_id' => $request->user()?->id], $request->file('attachment'), $request->ip());

        return redirect()->route('help.query')->with('status', "Thank you. Your query number is {$ticket->ticket_no}. We will reply to {$ticket->email}.");
    }

    public function chat(Request $request)
    {
        $ticket = $this->helpdesk->chatFor($request->user());
        $ticket?->update(['unread_for_user' => false]);

        return view('help.chat', [
            'ticket' => $ticket,
            'messages' => $ticket ? $ticket->messages()->get() : collect(),
            'past' => Ticket::where('user_id', $request->user()->id)->where('status', Ticket::CLOSED)->latest('id')->limit(5)->get(),
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'attachment' => Helpdesk::attachmentRules(),
        ], ['attachment.mimes' => 'Screenshots must be JPG or PNG images.']);

        $msg = $this->helpdesk->userMessage($request->user(), $data['message'], $request->file('attachment'), $request->ip());

        return $request->expectsJson() ? response()->json(['ok' => true, 'id' => $msg->id]) : redirect()->route('help.chat');
    }

    /** New messages since ?after=id (polled by the chat page). */
    public function messages(Request $request)
    {
        $ticket = $this->helpdesk->chatFor($request->user());
        if (! $ticket) {
            return response()->json(['messages' => []]);
        }
        $ticket->update(['unread_for_user' => false]);

        return response()->json(['messages' => $ticket->messages()->where('id', '>', $request->integer('after'))->get()->map(fn (TicketMessage $m) => [
            'id' => $m->id,
            'mine' => $m->sender === 'user',
            'sender' => $m->senderLabel(),
            'body' => $m->body,
            'time' => $m->created_at->timezone('Asia/Kolkata')->format('d M, h:i A'),
            'attachment' => $m->attachment_path ? route('help.attachment', $m) : null,
        ])]);
    }

    public function attachment(Request $request, TicketMessage $message)
    {
        abort_unless($message->attachment_path && $message->ticket->user_id === $request->user()->id, 404);

        return Storage::disk('local')->response($message->attachment_path, null, [
            'Content-Type' => $message->attachment_mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
