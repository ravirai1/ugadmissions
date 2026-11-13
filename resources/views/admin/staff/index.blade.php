@extends('layouts.admin')
@section('title', 'Staff Accounts')
@section('content')
@include('admin.staff._temp-password')
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
            <form class="d-flex flex-wrap gap-2" method="GET">
                <input class="form-control form-control-sm" style="width:200px" name="q" value="{{ request('q') }}" placeholder="Name or email">
                <select class="form-select form-select-sm" style="width:200px" name="role">
                    <option value="">All roles</option>
                    @foreach ($roles as $k => $v)<option value="{{ $k }}" @selected(request('role') === $k)>{{ $v }}</option>@endforeach
                </select>
                <select class="form-select form-select-sm" style="width:220px" name="department">
                    <option value="">All departments</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}" @selected((string) request('department') === (string) $d->id)>{{ $d->name }}</option>@endforeach
                </select>
                <button class="btn btn-primary btn-sm">Filter</button>
            </form>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.departments.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-building me-1"></i>Departments</a>
                <a href="{{ route('admin.staff.create') }}" class="btn btn-success btn-sm"><i class="fas fa-user-plus me-1"></i>New staff account</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Last login (IST)</th><th></th></tr></thead>
                <tbody>
                @forelse ($staff as $s)
                    <tr>
                        <td>{{ $s->name }}</td>
                        <td>{{ $s->email }}</td>
                        <td>{{ $roles[$s->role] ?? $s->role }}</td>
                        <td>{{ $s->department?->name ?? '–' }}</td>
                        <td>
                            @if ($s->is_active)<span class="badge badge-success">Active</span>@else<span class="badge badge-danger">Inactive</span>@endif
                            @if ($s->must_change_password)<span class="badge badge-warning">Must change password</span>@endif
                        </td>
                        <td class="small">{{ $s->last_login_at?->timezone('Asia/Kolkata')->format('d M Y h:i A') ?? 'Never' }}</td>
                        <td class="text-nowrap">
                            @unless ($s->isSuperAdmin())
                                <a href="{{ route('admin.staff.edit', $s) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.staff.reset', $s) }}" class="d-inline">@csrf<button class="btn btn-outline-warning btn-sm" type="submit">Reset password</button></form>
                                @unless ($s->is(auth('admin')->user()))
                                    <form method="POST" action="{{ route('admin.staff.toggle', $s) }}" class="d-inline">@csrf<button class="btn btn-outline-{{ $s->is_active ? 'danger' : 'success' }} btn-sm" type="submit">{{ $s->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                                @endunless
                            @else
                                <span class="small text-muted">Managed from the command line</span>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No staff accounts.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $staff->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
