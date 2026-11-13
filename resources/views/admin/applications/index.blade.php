@extends('layouts.admin')
@section('title', 'Applications')
@section('content')
<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-3"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="App no, CSAS no, CUET no, email, mobile, name"></div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="fee">
                    <option value="">Paid and unpaid</option>
                    <option value="paid" @selected(request('fee') === 'paid')>Fee paid</option>
                    <option value="unpaid" @selected(request('fee') === 'unpaid')>Fee unpaid</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" name="course">
                    <option value="">All courses</option>
                    @foreach ($courses as $id => $name)<option value="{{ $id }}" @selected((string) request('course') === (string) $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="category">
                    <option value="">All categories</option>
                    @foreach ($categories as $code => $name)<option value="{{ $code }}" @selected(request('category') === $code)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="subcategory">
                    <option value="">Christian: any</option>
                    @foreach ($subcategories as $code => $name)<option value="{{ $code }}" @selected(request('subcategory') === $code)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="gender">
                    <option value="">Any gender</option>
                    @foreach ($genders as $g)<option value="{{ $g }}" @selected(request('gender') === $g)>{{ $g }}</option>@endforeach
                </select>
            </div>
            @foreach (['state' => 'Current state', 'perm_state' => 'Permanent state', 'domicile' => 'State belongs to'] as $param => $label)
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="{{ $param }}">
                        <option value="">{{ $label }}: any</option>
                        @foreach ($states as $id => $name)<option value="{{ $id }}" @selected((string) request($param) === (string) $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-md-4 d-flex align-items-center gap-3 small">
                @foreach (['residence' => 'Residence', 'pwbd' => 'PwBD', 'cst' => 'CST'] as $param => $label)
                    <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="{{ $param }}" value="1" @checked(request($param))>{{ $label }}</label>
                @endforeach
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('admin.applications.index') }}" class="btn btn-link btn-sm">Clear</a>
                <a href="{{ route('admin.applications.export', request()->query()) }}" class="btn btn-success btn-sm ms-auto"><i class="fas fa-file-excel me-1"></i>Export to Excel</a>
            </div>
        </form>

        <p class="small text-muted mb-2">{{ number_format($apps->total()) }} application(s)</p>
        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
                <thead><tr><th>App no.</th><th>Name</th><th>Gender</th><th>Category</th><th>Courses (DU pref.)</th><th>Residence</th><th>Fee</th><th>Submitted (IST)</th></tr></thead>
                <tbody>
                @forelse ($apps as $a)
                    <tr>
                        <td><a href="{{ route('admin.applications.show', $a) }}">{{ $a->application_no }}</a></td>
                        <td>{{ $a->user->name }}<br><span class="small text-muted">{{ $a->user->cuet_no }}</span></td>
                        <td>{{ $a->gender }}</td>
                        <td>{{ $a->category_code }}{{ $a->christian_subcategory ? ' / '.$a->christian_subcategory : '' }}{{ $a->is_pwbd ? ' · PwBD' : '' }}</td>
                        <td class="small">{{ $a->courses->sortBy('du_preference')->map(fn ($c) => $c->course->name.' ('.$c->du_preference.')')->implode(', ') }}</td>
                        <td>{{ $a->residence ? 'Yes' : 'No' }}</td>
                        <td>@if ($a->isPaid())<span class="badge badge-success">Paid</span>@else<span class="badge badge-warning">Unpaid</span>@endif</td>
                        <td class="small text-nowrap">{{ $a->submitted_at?->timezone('Asia/Kolkata')->format('d M Y h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">No applications match.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $apps->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
