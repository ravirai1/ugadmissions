@extends('layouts.admin')
@section('title', 'Interviews: '.$course->name)
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.interviews.index') }}" class="btn btn-outline-secondary btn-sm">All courses</a>
    @if ($letters->isNotEmpty())<a href="{{ route('admin.print.call-letters', ['course_id' => $course->id]) }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="fas fa-print me-1"></i>Print all call letters</a>@endif
    <a href="{{ route('admin.merit.show', $course) }}" class="btn btn-outline-primary btn-sm">Merit list</a>
    <form method="POST" action="{{ route('admin.interviews.lock', $course) }}" class="ms-auto" data-confirm="{{ $locked ? 'Unlock marks? Seat allotment will leave this course out until it is finalised again.' : 'Finalise marks? Attendance and marks can no longer be changed, and the course goes into seat allotment.' }}">
        @csrf <input type="hidden" name="lock" value="{{ $locked ? 0 : 1 }}">
        <button class="btn btn-sm {{ $locked ? 'btn-outline-warning' : 'btn-success' }}" type="submit"><i class="fas {{ $locked ? 'fa-lock-open' : 'fa-lock' }} me-1"></i>{{ $locked ? 'Unlock marks' : 'Finalise marks' }}</button>
    </form>
</div>

<div class="card"><div class="card-body">
    <h5 class="card-title">Issue call letters <span class="small text-muted">({{ $pending->count() }} eligible without a letter)</span></h5>
    @if ($pending->isEmpty())
        <p class="text-muted mb-0">Everyone eligible has a call letter.</p>
    @else
        <form method="POST" action="{{ route('admin.interviews.issue', $course) }}">
            @csrf
            <div class="row g-2 mb-3">
                <div class="col-md-3"><label class="form-label small" for="interview_date">Interview date</label><input type="date" class="form-control form-control-sm" id="interview_date" name="interview_date" value="{{ old('interview_date') }}" required></div>
                <div class="col-md-2"><label class="form-label small" for="reporting_time">Reporting time</label><input class="form-control form-control-sm" id="reporting_time" name="reporting_time" value="{{ old('reporting_time', '09:00 AM') }}" required></div>
                <div class="col-md-5"><label class="form-label small" for="venue">Venue</label><input class="form-control form-control-sm" id="venue" name="venue" value="{{ old('venue', $venue) }}" required></div>
                <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="email" value="1" id="email" checked><label class="form-check-label small" for="email">Email letters</label></div></div>
            </div>
            <div class="table-responsive" style="max-height: 420px; overflow-y: auto">
                <table class="table table-sm table-compact">
                    <thead><tr><th><input type="checkbox" class="form-check-input" data-check-all="choices[]" aria-label="Select all"></th><th>Application</th><th>Name</th><th>Category</th><th class="text-end">DU pref.</th><th class="text-end">CUET (verified)</th></tr></thead>
                    @foreach ($pending as $c)
                        @php $s = $c->application->scrutinies->first(fn ($x) => $x->type === 'academic' && $x->application_course_id === $c->id); @endphp
                        <tr>
                            <td><input type="checkbox" class="form-check-input" name="choices[]" value="{{ $c->id }}" aria-label="Select {{ $c->application->application_no }}"></td>
                            <td>{{ $c->application->application_no }}</td>
                            <td>{{ $c->application->user->name }}</td>
                            <td>{{ $c->application->meritCategory() }}</td>
                            <td class="text-end">{{ $c->du_preference }}</td>
                            <td class="text-end">{{ $s?->verified_cuet_score ?? $c->cuet_score }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
            <button class="btn btn-primary btn-sm mt-2" type="submit"><i class="fas fa-paper-plane me-1"></i>Issue call letters to selected</button>
        </form>
    @endif
</div></div>

<div class="card"><div class="card-body">
    <h5 class="card-title">Call letters issued ({{ $letters->count() }})</h5>
    <div class="table-responsive">
        <table class="table table-sm table-compact align-middle">
            <thead><tr><th>Letter</th><th>Application</th><th>Name</th><th>Date</th><th>Attendance</th><th class="text-end">Interview (15)</th><th></th></tr></thead>
            @forelse ($letters as $l)
                <tr>
                    <td>{{ $l->letter_no }}</td>
                    <td>{{ $l->application->application_no }}</td>
                    <td>{{ $l->application->user->name }}</td>
                    <td>{{ $l->interview_date->format('d M Y') }}, {{ $l->reporting_time }}</td>
                    <td>@if ($l->attendance === 'present')<span class="badge bg-success">Present</span>@elseif ($l->attendance === 'absent')<span class="badge bg-danger">Absent</span>@else<span class="text-muted small">Not marked</span>@endif</td>
                    <td class="text-end">{{ $l->interviewScore() ?? '–' }} @if ($l->marks->isNotEmpty())<span class="small text-muted">({{ $l->marks->count() }} members)</span>@endif</td>
                    <td class="text-end">
                        @if (! $l->attendance && ! $locked)
                            <form method="POST" action="{{ route('admin.interviews.cancel', $l) }}" data-confirm="Cancel call letter {{ $l->letter_no }}?">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm" type="submit">Cancel</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">No call letters yet.</td></tr>
            @endforelse
        </table>
    </div>
</div></div>
@endsection
