@extends('layouts.admin')
@section('title', 'Login Attempts')
@section('content')
<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search IP or email"></div>
            <div class="col-md-3">
                <select class="form-select" name="guard">
                    <option value="">All logins</option>
                    <option value="admin" @selected(request('guard') === 'admin')>Staff</option>
                    <option value="applicant" @selected(request('guard') === 'applicant')>Applicant</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">Any result</option>
                    <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                    <option value="success" @selected(request('status') === 'success')>Successful</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead><tr><th>Time</th><th>IP</th><th>Login</th><th>Email</th><th>Result</th><th>Browser</th></tr></thead>
                <tbody>
                @forelse ($attempts as $a)
                    <tr>
                        <td>{{ $a->created_at->timezone('Asia/Kolkata')->format('d M Y h:i:s A') }}</td>
                        <td>{{ $a->ip }}</td>
                        <td>{{ $a->guard === 'admin' ? 'Staff' : 'Applicant' }}</td>
                        <td>{{ $a->email }}</td>
                        <td>@if ($a->successful)<span class="badge badge-success">Success</span>@else<span class="badge badge-danger">Failed</span>@endif</td>
                        <td class="small text-muted text-truncate" style="max-width:240px">{{ $a->user_agent }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No login attempts.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $attempts->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
