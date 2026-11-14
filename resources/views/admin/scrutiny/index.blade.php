@extends('layouts.admin')
@section('title', ($type === 'academic' ? 'Academic' : 'Religious').' Scrutiny')
@section('content')
@php
    $badge = ['pending' => 'warning', 'query' => 'info', 'review' => 'primary', 'approved' => 'success', 'rejected' => 'danger'];
    $total = $counts->sum();
@endphp
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.scrutiny.index', $type) }}" class="btn btn-sm {{ ! request('status') ? 'btn-primary' : 'btn-outline-secondary' }}">To do ({{ ($counts['pending'] ?? 0) + ($counts['query'] ?? 0) }})</a>
    <a href="{{ route('admin.scrutiny.index', [$type, 'status' => 'reply']) }}" class="btn btn-sm {{ request('status') === 'reply' ? 'btn-primary' : 'btn-outline-secondary' }}">Replies received ({{ $replies }})</a>
    @foreach (\App\Models\Scrutiny::STATUSES as $k => $label)
        <a href="{{ route('admin.scrutiny.index', [$type, 'status' => $k]) }}" class="btn btn-sm {{ request('status') === $k ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }} ({{ $counts[$k] ?? 0 }})</a>
    @endforeach
    <span class="ms-auto small text-muted align-self-center">Total {{ $total }} · <span data-autorefresh="60">refreshes every minute</span></span>
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <div class="col-md-3"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="App no, CUET no or name"></div>
            @if ($type === 'academic')
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="course">
                        <option value="">All courses</option>
                        @foreach ($courses as $id => $name)<option value="{{ $id }}" @selected((string) request('course') === (string) $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                @if ($departments->isNotEmpty())
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" name="department">
                            <option value="">All departments</option>
                            @foreach ($departments as $id => $name)<option value="{{ $id }}" @selected((string) request('department') === (string) $id)>{{ $name }}</option>@endforeach
                        </select>
                    </div>
                @endif
            @endif
            <div class="col-md-2"><button class="btn btn-primary btn-sm">Filter</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
                <thead><tr><th>App no.</th><th>Name</th>@if ($type === 'academic')<th>Course</th><th>DU pref.</th><th>CUET total</th>@else<th>Category</th><th>CST</th>@endif<th>Status</th><th>Reviewer</th><th></th></tr></thead>
                <tbody>
                @forelse ($items as $s)
                    @php $a = $s->application; @endphp
                    <tr>
                        <td>{{ $a->application_no }}</td>
                        <td>{{ $a->user->name }}</td>
                        @if ($type === 'academic')
                            <td>{{ $s->applicationCourse?->course->name }}</td>
                            <td>{{ $s->applicationCourse?->du_preference }}</td>
                            <td>{{ $s->applicationCourse?->cuet_score + 0 }}</td>
                        @else
                            <td>{{ $a->christian_subcategory }}</td>
                            <td>{{ $a->is_cst ? 'Yes' : 'No' }}</td>
                        @endif
                        <td>
                            <span class="badge badge-{{ $badge[$s->status] ?? 'secondary' }}">{{ $s->statusLabel() }}</span>
                            @if ($s->reply_received)<span class="badge badge-primary">Reply received</span>@endif
                        </td>
                        <td class="small">{{ $s->reviewer?->name ?? '–' }}</td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.scrutiny.show', $s) }}">{{ $s->isDecided() ? 'View' : 'Scrutinise' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">Nothing here. Paid applications appear automatically.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('admin-assets/js/ssc-autorefresh.js') }}"></script>
@endpush
