@extends('layouts.admin')
@section('title', $staff->exists ? 'Edit Staff Account' : 'New Staff Account')
@section('content')
@php $self = $staff->exists && $staff->is(auth('admin')->user()); @endphp
<div class="card" style="max-width: 760px">
    <div class="card-body">
        <form method="POST" action="{{ $staff->exists ? route('admin.staff.update', $staff) : route('admin.staff.store') }}">
            @csrf
            @if ($staff->exists) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">Full name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $staff->name) }}" required maxlength="150">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">College email</label>
                    <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $staff->email) }}" required maxlength="150">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="mobile">Mobile (optional)</label>
                    <input class="form-control" id="mobile" name="mobile" value="{{ old('mobile', $staff->mobile) }}" maxlength="10" inputmode="numeric">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="role">Role</label>
                    <select class="form-select" id="role" name="role" @disabled($self) required>
                        <option value="">Select</option>
                        @foreach ($roles as $k => $v)<option value="{{ $k }}" @selected(old('role', $staff->role) === $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="department_id">Department</label>
                    <select class="form-select" id="department_id" name="department_id">
                        <option value="">None</option>
                        @foreach ($departments as $d)<option value="{{ $d->id }}" @selected((string) old('department_id', $staff->department_id) === (string) $d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                    <div class="form-text">Required for Department Faculty and Interview Panel: they see only their department's applicants.</div>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $staff->is_active)) @disabled($self)>
                        <label class="form-check-label" for="is_active">Account active</label>
                    </div>
                </div>
            </div>
            @unless ($staff->exists)
                <p class="small text-muted mt-3 mb-0">A temporary password is generated and shown once after saving. The person must change it at first login.</p>
            @endunless
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save</button>
                <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
