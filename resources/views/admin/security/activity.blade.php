@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="card">
    <div class="card-body">
        <p class="small text-muted">
            @if ($who === 'applicant')
                Every applicant login, logout and failed login, every save, upload, submission, payment and query reply, and each call letter, offer letter or receipt opened. Form contents are never stored.
            @else
                Every staff login, logout and failed login, and every page opened or action taken in the staff panel, so you can see who looked at which application. Form contents are never stored.
            @endif
            Kept for {{ config('admissions.activity_log_days') }} days.
        </p>
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="{{ $who === 'applicant' ? 'Name, email, CUET no., mobile, IP or page' : 'Name, email, IP or page' }}"></div>
            <div class="col-md-2">
                <select class="form-select" name="event">
                    <option value="">All events</option>
                    @foreach (['login' => 'Logins', 'login_failed' => 'Failed logins', 'logout' => 'Logouts'] + ($who === 'applicant' ? ['application.' => 'Application form', 'payment.' => 'Payments', 'applicant.' => 'Letters & queries'] : ['admin.applications' => 'Applications', 'admin.scrutiny' => 'Scrutiny', 'admin.payments' => 'Payments', 'admin.staff' => 'Staff accounts', 'admin.security' => 'Security pages']) as $v => $l)
                        <option value="{{ $v }}" @selected(request('event') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><input type="date" class="form-control" name="from" value="{{ request('from') }}" title="From"></div>
            <div class="col-md-2"><input type="date" class="form-control" name="to" value="{{ request('to') }}" title="To"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped table-sm small">
                <thead><tr><th>Time (IST)</th><th>{{ $who === 'applicant' ? 'Applicant' : 'Staff' }}</th><th>Event</th><th>Page</th><th>Result</th><th>IP</th><th>Browser</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    @php($person = $who === 'applicant' ? $log->user : $log->admin)
                    <tr class="{{ $log->event === 'login_failed' || ($log->status ?? 0) >= 400 ? 'table-warning' : '' }}">
                        <td class="text-nowrap">{{ $log->created_at->timezone('Asia/Kolkata')->format('d M Y h:i:s A') }}</td>
                        <td>@if ($person){{ $person->name }}<br><span class="text-muted">{{ $who === 'applicant' ? $person->cuet_no : $person->email }}</span>@else<span class="text-muted">{{ $log->login_id ?? 'Unknown' }}</span>@endif</td>
                        <td><code>{{ $log->event }}</code></td>
                        <td>@if ($log->path){{ $log->method }} {{ \Illuminate\Support\Str::limit($log->path, 60) }}@endif</td>
                        <td>{{ $log->status }}</td>
                        <td>{{ $log->ip }}</td>
                        <td class="text-muted" title="{{ $log->user_agent }}">{{ \Illuminate\Support\Str::limit($log->user_agent, 30) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No entries.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
