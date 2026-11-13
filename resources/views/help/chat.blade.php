@extends('layouts.frontend')
@section('title', 'Chat with Admissions')
@section('page_title', 'Chat with Admissions')
@section('content')
<div class="container" style="max-width: 820px">
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    <p class="small text-muted">Write to the admissions team. Replies appear here (this page updates by itself) and are also emailed to you. Office hours: Mon–Fri, 9:30 AM – 4:30 PM.@if ($ticket) Ticket {{ $ticket->ticket_no }}.@endif</p>
    <div class="chat-box mb-3" data-chat-feed="{{ route('help.chat.messages') }}" data-after="{{ $messages->last()?->id ?? 0 }}">
        @forelse ($messages as $m)
            <div class="chat-msg {{ $m->sender === 'user' ? 'mine' : 'theirs' }}">
                <div class="chat-meta">{{ $m->senderLabel() }} · {{ $m->created_at->timezone('Asia/Kolkata')->format('d M, h:i A') }}</div>
                <div class="chat-body">{{ $m->body }}</div>
                @if ($m->attachment_path)<a class="chat-att" href="{{ route('help.attachment', $m) }}" target="_blank" rel="noopener">Attachment</a>@endif
            </div>
        @empty
            <p class="text-muted small mb-0">No messages yet. How can we help?</p>
        @endforelse
    </div>
    <form method="POST" action="{{ route('help.chat.send') }}" enctype="multipart/form-data" class="d-flex flex-column gap-2">
        @csrf
        <textarea class="form-control" name="message" rows="3" maxlength="2000" placeholder="Type your message" required></textarea>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <input type="file" class="form-control form-control-sm" style="max-width: 320px" name="attachment" accept=".jpg,.jpeg,.png" aria-label="Screenshot (optional)">
            <span class="small text-muted">Screenshot (optional, JPG/PNG, 2 MB)</span>
            <button class="btn btn-primary ms-auto px-4" type="submit"><i class="fa fa-paper-plane me-1"></i>Send</button>
        </div>
    </form>
    @if ($past->isNotEmpty())
        <p class="small text-muted mt-4 mb-1">Earlier conversations (closed): {{ $past->pluck('ticket_no')->implode(', ') }}</p>
    @endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('frontend/js/ssc-chat.js') }}"></script>
@endpush
