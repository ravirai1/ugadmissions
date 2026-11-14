@extends('layouts.admin')
@section('title', 'Attack Attempts')
@section('content')
<div class="card">
    <div class="card-body">
        <p class="small text-muted mb-3">
            Requests that looked like an attack (SQL injection, script injection, path probing, scanner tools, forged forms) were refused and saved here.
            An IP with {{ config('security.auto_block.max_events') }} or more within {{ config('security.auto_block.window_minutes') }} minutes is blocked from the whole site for {{ config('security.auto_block.block_minutes') }} minutes. Manage blocks under <a href="{{ route('admin.security.blocked') }}">Blocked IPs</a>.
        </p>
        <div class="alert {{ ['ok' => 'alert-success', 'alert' => 'alert-danger'][$integrity['state'] ?? ''] ?? 'alert-warning' }} small">
            <strong>Website files:</strong>
            @if ($integrity)
                {{ $integrity['message'] }} <span class="text-muted">(checked {{ \Illuminate\Support\Carbon::parse($integrity['checked_at'])->timezone('Asia/Kolkata')->format('d M, h:i A') }}, every 5 minutes)</span>
                @if (! empty($integrity['lines']))<ul class="mb-0 mt-1">@foreach ($integrity['lines'] as $l)<li><code>{{ $l }}</code></li>@endforeach</ul>@endif
            @else
                Not checked yet. After each deploy run <code>php artisan security:integrity --baseline</code>; the scheduler then checks every 5 minutes.
            @endif
        </div>
        @if ($top->isNotEmpty())
            <p class="small mb-3"><strong>Most active in the last 24 hours:</strong>
                @foreach ($top as $t)<a href="?q={{ urlencode($t->ip) }}" class="badge bg-danger text-decoration-none me-1">{{ $t->ip }} ({{ $t->n }})</a>@endforeach
            </p>
        @endif
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="IP or page"></div>
            <div class="col-md-3">
                <select class="form-select" name="type">
                    <option value="">All types</option>
                    @foreach (\App\Models\SecurityEvent::TYPES as $v => $l)<option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped table-sm small">
                <thead><tr><th>Time (IST)</th><th>Type</th><th>IP</th><th>Request</th><th>Field</th><th>What was sent (shortened)</th><th>Blocked</th></tr></thead>
                <tbody>
                @forelse ($events as $e)
                    <tr>
                        <td class="text-nowrap">{{ $e->created_at->timezone('Asia/Kolkata')->format('d M Y h:i:s A') }}</td>
                        <td><span class="badge bg-danger">{{ \App\Models\SecurityEvent::TYPES[$e->type] ?? $e->type }}</span></td>
                        <td>{{ $e->ip }}</td>
                        <td>{{ $e->method }} {{ \Illuminate\Support\Str::limit($e->path, 50) }}</td>
                        <td><code>{{ $e->field }}</code></td>
                        <td><code>{{ $e->sample }}</code></td>
                        
                        <td>{{ $e->caused_block ? 'Yes' : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No attack attempts recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $events->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
