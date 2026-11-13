@extends('layouts.admin')
@section('title', 'Departments')
@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.departments.store') }}" class="row g-2 mb-4">
            @csrf
            <div class="col-md-6"><input class="form-control" name="name" placeholder="Department name, e.g. Department of Physics" value="{{ old('name') }}" required></div>
            <div class="col-md-3"><input class="form-control" name="code" placeholder="Code, e.g. PHY" value="{{ old('code') }}" required></div>
            <div class="col-md-3"><button class="btn btn-success w-100" type="submit"><i class="fas fa-plus me-1"></i>Add department</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Name</th><th>Code</th><th>Courses</th><th>Staff</th><th>Active</th><th></th></tr></thead>
                <tbody>
                @foreach ($departments as $d)
                    <tr>
                        <td><input class="form-control form-control-sm" name="name" value="{{ $d->name }}" required form="dept{{ $d->id }}" aria-label="Name"></td>
                        <td style="width:140px"><input class="form-control form-control-sm" name="code" value="{{ $d->code }}" required form="dept{{ $d->id }}" aria-label="Code"></td>
                        <td>{{ $d->courses_count }}</td>
                        <td>{{ $d->admins_count }}</td>
                        <td><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($d->is_active) form="dept{{ $d->id }}" aria-label="Active"></td>
                        <td>
                            <form method="POST" action="{{ route('admin.departments.update', $d) }}" id="dept{{ $d->id }}">
                                @csrf @method('PUT')
                                <button class="btn btn-outline-primary btn-sm" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary btn-sm">Back to staff</a>
    </div>
</div>
@endsection
