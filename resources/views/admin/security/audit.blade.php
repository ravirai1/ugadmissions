@extends('layouts.admin')
@section('title', 'Audit Log')
@section('content')
<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4"><input class="form-control" name="action" value="{{ request('action') }}" placeholder="Action starts with (e.g. auth., security.)"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead><tr><th>Time</th><th>Staff</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->timezone('Asia/Kolkata')->format('d M Y h:i:s A') }}</td>
                        <td>{{ $log->admin?->name ?? 'System' }}</td>
                        <td><code>{{ $log->action }}</code></td>
                        <td class="small">{{ $log->meta ? json_encode($log->meta) : '' }}</td>
                        <td>{{ $log->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">No entries.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
