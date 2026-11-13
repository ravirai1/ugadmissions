@extends('layouts.admin')
@section('title', 'Ticket '.$ticket->ticket_no)
@section('content')
<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-1">{{ $ticket->subject }}</h5>
                <p class="small text-muted">{{ ucfirst($ticket->channel) }} · {{ \App\Models\Ticket::STATUSES[$ticket->status] }} · opened {{ $ticket->created_at->timezone('Asia/Kolkata')->format('d M Y h:i A') }}</p>
                <div class="chat-box mb-3" data-chat-feed="{{ route('admin.helpdesk.messages', $ticket) }}" data-after="{{ $ticket->messages->last()?->id ?? 0 }}" data-interval="6">
                    @foreach ($ticket->messages as $m)
                        <div class="chat-msg {{ $m->sender === 'staff' ? 'mine' : 'theirs' }}">
                            <div class="chat-meta">{{ $m->sender === 'staff' ? ($m->admin?->name ?? 'Staff') : $ticket->name }} · {{ $m->created_at->timezone('Asia/Kolkata')->format('d M, h:i A') }}</div>
                            <div class="chat-body">{{ $m->body }}</div>
                            @if ($m->attachment_path)<a class="chat-att" href="{{ route('admin.helpdesk.attachment', $m) }}" target="_blank" rel="noopener">Attachment</a>@endif
                        </div>
                    @endforeach
                </div>
                @if ($ticket->status !== 'closed')
                    <form method="POST" action="{{ route('admin.helpdesk.reply', $ticket) }}" enctype="multipart/form-data">
                        @csrf
                        <textarea class="form-control mb-2" id="reply" name="message" rows="4" maxlength="3000" placeholder="Reply (also emailed to {{ $ticket->email }})" required></textarea>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input type="file" class="form-control form-control-sm" style="max-width: 300px" name="attachment" accept=".jpg,.jpeg,.png,.pdf" aria-label="Attachment">
                            <button class="btn btn-primary btn-sm ms-auto" type="submit"><i class="fas fa-paper-plane me-1"></i>Send reply</button>
                        </div>
                    </form>
                    <div class="mt-3">
                        <p class="small fw-bold mb-1">Quick replies</p>
                        @foreach ($quickReplies as $qr)
                            <button type="button" class="btn btn-outline-secondary btn-sm mb-1 text-start" data-quick-reply="{{ $qr }}" data-target="reply">{{ \Illuminate\Support\Str::limit($qr, 70) }}</button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body small">
                <h6 class="fw-bold">From</h6>
                <p class="mb-2">{{ $ticket->name }}<br>{{ $ticket->email }}@if ($ticket->mobile)<br>+91 {{ $ticket->mobile }}@endif</p>
                @if ($ticket->user?->application?->application_no)
                    <p class="mb-2">Application: <strong>{{ $ticket->user->application->application_no }}</strong>
                        @if (auth('admin')->user()->hasRole(...\App\Support\ApplicationAccess::CENTRAL))<a href="{{ route('admin.applications.show', $ticket->user->application) }}">open</a>@endif</p>
                @elseif ($ticket->user)
                    <p class="mb-2">Registered applicant (CUET {{ $ticket->user->cuet_no }}), no application submitted.</p>
                @endif
                <p class="mb-3">Assigned: <strong>{{ $ticket->assignee?->name ?? 'Nobody' }}</strong></p>
                <div class="d-flex flex-wrap gap-2">
                    @if ($ticket->assigned_to !== auth('admin')->id())
                        <form method="POST" action="{{ route('admin.helpdesk.assign', $ticket) }}">@csrf<button class="btn btn-outline-primary btn-sm" type="submit">Assign to me</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.helpdesk.status', $ticket) }}">@csrf
                        <input type="hidden" name="status" value="{{ $ticket->status === 'closed' ? 'open' : 'closed' }}">
                        <button class="btn btn-outline-{{ $ticket->status === 'closed' ? 'success' : 'danger' }} btn-sm" type="submit">{{ $ticket->status === 'closed' ? 'Reopen' : 'Close ticket' }}</button>
                    </form>
                </div>
            </div>
        </div>
        <a href="{{ route('admin.helpdesk.index') }}" class="btn btn-outline-secondary btn-sm">Back to inbox</a>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('frontend/js/ssc-chat.js') }}"></script>
@endpush
