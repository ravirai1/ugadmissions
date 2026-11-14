<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\Helpdesk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HelpdeskController extends Controller
{
    public function __construct(private Helpdesk $helpdesk) {}

    public function index(Request $request)
    {
        $admin = $request->user('admin');
        $q = Ticket::with('assignee')->withCount('messages');

        $status = $request->query('status', 'active');
        match ($status) {
            'active' => $q->whereIn('status', [Ticket::OPEN, Ticket::ANSWERED]),
            'all' => null,
            default => $q->where('status', $status),
        };
        if ($c = $request->query('channel')) {
            $q->where('channel', $c);
        }
        if ($request->boolean('mine')) {
            $q->where('assigned_to', $admin->id);
        }
        if ($request->boolean('unread')) {
            $q->where('unread_for_staff', true);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('ticket_no', $term)->orWhere('email', $term)->orWhere('mobile', $term)
                ->orWhere('name', 'like', '%'.addcslashes($term, '%_\\').'%'));
        }

        $avgFirst = Ticket::whereNotNull('first_response_at')->get(['created_at', 'first_response_at'])
            ->avg(fn ($t) => $t->created_at->diffInMinutes($t->first_response_at));

        return view('admin.helpdesk.index', [
            'tickets' => $q->orderByDesc('unread_for_staff')->orderByDesc('last_message_at')->paginate(30)->withQueryString(),
            'status' => $status,
            'stats' => [
                'unread' => Ticket::where('unread_for_staff', true)->where('status', '!=', Ticket::CLOSED)->count(),
                'open' => Ticket::where('status', Ticket::OPEN)->count(),
                'answered' => Ticket::where('status', Ticket::ANSWERED)->count(),
                'closed' => Ticket::where('status', Ticket::CLOSED)->count(),
                'today' => Ticket::whereDate('created_at', today())->count(),
                'avg_first_response' => $avgFirst ? round($avgFirst) : null,
            ],
        ]);
    }

    public function show(Ticket $ticket)
    {
        $ticket->update(['unread_for_staff' => false]);

        return view('admin.helpdesk.show', [
            'ticket' => $ticket->load('messages.admin', 'assignee', 'user.application'),
            'quickReplies' => config('admissions.quick_replies'),
        ]);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:3000'], 'attachment' => Helpdesk::attachmentRules(staff: true)]);
        $this->helpdesk->staffReply($ticket, $request->user('admin'), $data['message'], $request->file('attachment'));

        return $request->expectsJson() ? response()->json(['ok' => true]) : redirect()->route('admin.helpdesk.show', $ticket)->with('status', 'Reply sent.');
    }

    public function messages(Request $request, Ticket $ticket)
    {
        $ticket->update(['unread_for_staff' => false]);

        return response()->json(['messages' => $ticket->messages()->with('admin')->where('id', '>', $request->integer('after'))->get()->map(fn (TicketMessage $m) => [
            'id' => $m->id,
            'mine' => $m->sender === 'staff',
            'sender' => $m->sender === 'staff' ? ($m->admin?->name ?? 'Staff') : $ticket->name,
            'body' => $m->body,
            'time' => $m->created_at->timezone('Asia/Kolkata')->format('d M, h:i A'),
            'attachment' => $m->attachment_path ? route('admin.helpdesk.attachment', $m) : null,
        ])]);
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $ticket->update(['assigned_to' => $request->user('admin')->id]);

        return back()->with('status', 'Assigned to you.');
    }

    public function status(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['status' => ['required', 'in:open,closed']]);
        $ticket->update(['status' => $data['status']]);
        AuditLog::record('helpdesk.'.$data['status'], $ticket);

        return back()->with('status', $data['status'] === 'closed' ? 'Ticket closed.' : 'Ticket reopened.');
    }

    public function attachment(TicketMessage $message)
    {
        abort_unless($message->attachment_path, 404);

        return Storage::disk('documents')->response($message->attachment_path, null, [
            'Content-Type' => $message->attachment_mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Daily counts for the last 14 days (report). */
    public function report()
    {
        $from = now('Asia/Kolkata')->subDays(13)->startOfDay();
        $rows = Ticket::where('created_at', '>=', $from->copy()->utc())->get(['created_at', 'channel', 'first_response_at'])
            ->groupBy(fn ($t) => $t->created_at->timezone('Asia/Kolkata')->toDateString());
        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now('Asia/Kolkata')->subDays($i)->toDateString();
            $list = $rows[$d] ?? collect();
            $days[] = ['date' => $d, 'chat' => $list->where('channel', 'chat')->count(), 'query' => $list->where('channel', 'query')->count(),
                'avg' => ($r = $list->filter(fn ($t) => $t->first_response_at))->isNotEmpty() ? round($r->avg(fn ($t) => $t->created_at->diffInMinutes($t->first_response_at))) : null];
        }
        $byStaff = DB::table('ticket_messages')->join('admins', 'admins.id', '=', 'ticket_messages.admin_id')
            ->where('sender', 'staff')->groupBy('admins.name')->select('admins.name', DB::raw('count(*) as n'))->orderByDesc('n')->get();

        return view('admin.helpdesk.report', ['days' => $days, 'byStaff' => $byStaff]);
    }
}
