@extends('layouts.admin')
@section('title', 'Selected List')
@section('content')
@php $ist = fn ($d) => $d?->timezone('Asia/Kolkata')->format('d M Y, h:i A'); @endphp
<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
    @foreach (\App\Models\SeatOffer::STATUSES as $k => $label)<span>{{ $label }}: <strong>{{ $totals[$k] ?? 0 }}</strong></span>@endforeach
    <a href="{{ route('admin.allotment.export') }}" class="btn btn-success btn-sm ms-auto"><i class="fas fa-file-excel me-1"></i>Download selected list (Excel)</a>
</div>

<div class="card"><div class="card-body">
    <h5 class="card-title">Category-wise (accepted / offered / seats)</h5>
    <div class="table-responsive">
        <table class="table table-sm stat-table">
            <thead><tr><th>Course</th>@foreach ($cats as $c)<th class="text-end" title="{{ $categoryNames[$c] ?? $c }}">{{ $c }}</th>@endforeach<th class="text-end">Total</th></tr></thead>
            @foreach ($courses as $course)
                @php $m = $matrix[$course->id]; @endphp
                <tr>
                    <td>{{ $course->name }}</td>
                    @foreach ($cats as $c)
                        <td class="text-end"><strong>{{ $m['accepted'][$c] ?? 0 }}</strong> / {{ $m['offered'][$c] ?? 0 }} / {{ $m['seats'][$c] ?? 0 }}</td>
                    @endforeach
                    <td class="text-end"><strong>{{ array_sum($m['accepted']) }}</strong> / {{ array_sum($m['offered']) }} / {{ array_sum($m['seats']) }}</td>
                </tr>
            @endforeach
        </table>
    </div>
</div></div>

<div class="card"><div class="card-body">
    <form class="row g-2 mb-3" method="GET">
        <div class="col-md-4">
            <select class="form-select form-select-sm" name="course" aria-label="Course">
                <option value="">All courses</option>
                @foreach ($courses as $c)<option value="{{ $c->id }}" @selected((string) request('course') === (string) $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" name="status" aria-label="Status">
                <option value="">Any status</option>
                @foreach (\App\Models\SeatOffer::STATUSES as $k => $label)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary btn-sm" type="submit">Filter</button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-compact align-middle">
            <thead><tr><th>Offer</th><th>Round</th><th>Course</th><th>Seat</th><th class="text-end">Rank</th><th>Application</th><th>Name</th><th class="text-end">Total (100)</th><th>Status</th><th>Deadline</th></tr></thead>
            @forelse ($offers as $o)
                <tr>
                    <td>{{ $o->offer_no }}</td><td>{{ $o->round }}</td><td class="small">{{ $o->course->name }}</td><td>{{ $o->seat_category }}</td><td class="text-end">{{ $o->merit_rank }}</td>
                    <td><a href="{{ route('admin.applications.show', $o->application) }}">{{ $o->application->application_no }}</a></td><td>{{ $o->application->user->name }}</td>
                    <td class="text-end">{{ $o->total }}</td>
                    <td><span class="badge {{ ['accepted' => 'bg-success', 'offered' => 'bg-primary', 'declined' => 'bg-danger', 'lapsed' => 'bg-warning text-dark'][$o->status] }}">{{ \App\Models\SeatOffer::STATUSES[$o->status] }}</span></td>
                    <td class="small">{{ $ist($o->deadline_at) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-muted">No offers yet.</td></tr>
            @endforelse
        </table>
    </div>
    {{ $offers->links() }}
</div></div>
@endsection
