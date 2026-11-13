@extends('layouts.admin')
@section('title', 'Helpdesk')
@section('content')
<div class="row">
    @foreach ([['Unread', $stats['unread'], 'danger', ['unread' => 1]], ['Open', $stats['open'], 'warning', ['status' => 'open']], ['Answered', $stats['answered'], 'success', ['status' => 'answered']], ['Closed', $stats['closed'], 'secondary', ['status' => 'closed']], ['New today', $stats['today'], 'info', ['status' => 'all']]] as [$label, $n, $c, $f])
        <div class="col-6 col-md">
            <a href="{{ route('admin.helpdesk.index', $f) }}" class="text-decoration-none"><div class="card card-stats card-round"><div class="card-body py-3"><p class="card-category mb-1 text-{{ $c }}">{{ $label }}</p><h4 class="card-title mb-0">{{ $n }}</h4></div></div></a>
        </div>
    @endforeach
    <div class="col-6 col-md"><div class="card card-stats card-round"><div class="card-body py-3"><p class="card-category mb-1">Avg. first reply</p><h4 class="card-title mb-0">{{ $stats['avg_first_response'] !== null ? $stats['avg_first_response'].' min' : '–' }}</h4></div></div></div>
</div>
<div class="card">
    <div class="card-body">
        <form class="d-flex flex-wrap gap-2 mb-3" method="GET">
            <input class="form-control form-control-sm" style="width:240px" name="q" value="{{ request('q') }}" placeholder="Ticket no, email, mobile, name">
            <select class="form-select form-select-sm" style="width:170px" name="status">
                @foreach (['active' => 'Open + answered', 'open' => 'Open', 'answered' => 'Answered', 'closed' => 'Closed', 'all' => 'All'] as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach
            </select>
            <select class="form-select form-select-sm" style="width:150px" name="channel">
                <option value="">Chat + queries</option>
                <option value="chat" @selected(request('channel') === 'chat')>Chat</option>
                <option value="query" @selected(request('channel') === 'query')>Queries</option>
            </select>
            <label class="small align-self-center"><input type="checkbox" class="form-check-input me-1" name="mine" value="1" @checked(request('mine'))>Assigned to me</label>
            <button class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('admin.helpdesk.report') }}" class="btn btn-outline-secondary btn-sm ms-auto">Report</a>
        </form>
        <div class="table-responsive" data-autorefresh="45">
            <table class="table table-sm table-striped align-middle">
                <thead><tr><th>Ticket</th><th>From</th><th>Subject</th><th>Type</th><th>Status</th><th>Assigned</th><th>Last message (IST)</th></tr></thead>
                <tbody>
                @forelse ($tickets as $t)
                    <tr class="{{ $t->unread_for_staff && $t->status !== 'closed' ? 'fw-bold' : '' }}">
                        <td><a href="{{ route('admin.helpdesk.show', $t) }}">{{ $t->ticket_no }}</a>@if ($t->unread_for_staff && $t->status !== 'closed') <span class="badge badge-danger">new</span>@endif</td>
                        <td>{{ $t->name }}<br><span class="small text-muted">{{ $t->email }}</span></td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($t->subject, 60) }}</td>
                        <td>{{ ucfirst($t->channel) }}</td>
                        <td>{{ \App\Models\Ticket::STATUSES[$t->status] }}</td>
                        <td class="small">{{ $t->assignee?->name ?? '–' }}</td>
                        <td class="small text-nowrap">{{ $t->last_message_at?->timezone('Asia/Kolkata')->format('d M h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No tickets.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $tickets->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('admin-assets/js/ssc-autorefresh.js') }}"></script>
@endpush
