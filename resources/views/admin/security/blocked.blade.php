@extends('layouts.admin')
@section('title', 'Blocked IPs')
@section('content')
<div class="card">
    <div class="card-header"><div class="card-title">Block an IP manually</div></div>
    <div class="card-body">
        <form class="row g-2" method="POST" action="{{ route('admin.security.block') }}">
            @csrf
            <div class="col-md-3"><input class="form-control" name="ip" placeholder="IP address" required></div>
            <div class="col-md-2">
                <select class="form-select" name="guard"><option value="admin">Staff login</option><option value="applicant">Applicant login</option></select>
            </div>
            <div class="col-md-3"><input class="form-control" name="reason" placeholder="Reason" maxlength="200"></div>
            <div class="col-md-2"><input class="form-control" type="number" name="minutes" min="1" placeholder="Minutes (blank = permanent)"></div>
            <div class="col-md-2"><button class="btn btn-danger w-100">Block</button></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>IP</th><th>Login</th><th>Reason</th><th>Blocked until</th><th></th></tr></thead>
                <tbody>
                @forelse ($blocked as $b)
                    <tr>
                        <td>{{ $b->ip }}</td>
                        <td>{{ $b->guard === 'admin' ? 'Staff' : 'Applicant' }}</td>
                        <td>{{ $b->reason }}</td>
                        <td>{{ $b->blocked_until ? $b->blocked_until->timezone('Asia/Kolkata')->format('d M Y h:i A') : 'Permanent' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.security.unblock', $b) }}" data-confirm="Unblock {{ $b->ip }}?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-success">Unblock</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">No IPs are blocked.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $blocked->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
