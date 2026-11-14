@extends('layouts.admin')
@section('title', 'Interview Marks')
@section('content')
<div class="card"><div class="card-body">
    @if (! $course)
        <p class="text-muted mb-0">No courses are linked to your department. Ask the admin to set your department.</p>
    @else
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-5">
                <select class="form-select form-select-sm" name="course" aria-label="Course">
                    @foreach ($courses as $c)<option value="{{ $c->id }}" @selected($c->id === $course->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" name="date" aria-label="Interview date">
                    @forelse ($dates as $d)<option value="{{ $d }}" @selected($d === $date)>{{ \Illuminate\Support\Carbon::parse($d)->format('d M Y') }}</option>@empty<option value="">No interviews yet</option>@endforelse
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary btn-sm" type="submit">Show</button></div>
        </form>

        <p class="small text-muted">Enter your own marks out of <strong>{{ $max }}</strong> (half marks allowed) for each candidate marked present. Each panel member's marks are kept separately and the merit list uses the average: CUET (85) + interview ({{ $max }}) = 100.</p>
        @if ($locked)<div class="alert alert-info py-2">Marks for this course are finalised and can no longer be changed.</div>@endif

        <form method="POST" action="{{ route('admin.panel.save', $course) }}">
            @csrf
            <div class="table-responsive">
                <table class="table table-sm table-compact align-middle">
                    <thead><tr><th>Letter</th><th>Name</th><th>Application</th><th>Attendance</th><th style="width:110px">My marks (/{{ $max }})</th><th>My remarks</th>@if ($seeAll)<th>All members</th><th class="text-end">Average (/{{ $max }})</th>@else<th class="text-end">Members scored</th>@endif</tr></thead>
                    @forelse ($letters as $l)
                        @php $mine = $l->marks->firstWhere('admin_id', $me->id); $present = $l->attendance === 'present'; @endphp
                        <tr class="{{ $present ? '' : 'text-muted' }}">
                            <td>{{ $l->letter_no }}</td>
                            <td>{{ $l->application->user->name }}<br><a class="small" href="{{ route('admin.applications.show', $l->application) }}" target="_blank" rel="noopener"><i class="fas fa-user me-1"></i>View profile</a></td>
                            <td>{{ $l->application->application_no }}</td>
                            <td>{{ $l->attendance ? ucfirst($l->attendance) : 'Not marked' }}</td>
                            <td><input type="number" class="form-control form-control-sm" name="marks[{{ $l->id }}]" value="{{ old('marks.'.$l->id, $mine?->marks) }}" min="0" max="{{ $max }}" step="0.5" aria-label="Marks for {{ $l->letter_no }}" @disabled(! $present || $locked)></td>
                            <td><input class="form-control form-control-sm" name="remarks[{{ $l->id }}]" value="{{ old('remarks.'.$l->id, $mine?->remarks) }}" maxlength="300" aria-label="Remarks for {{ $l->letter_no }}" @disabled(! $present || $locked)></td>
                            @if ($seeAll)
                                <td class="small">@foreach ($l->marks as $m){{ $m->admin->name }}: {{ $m->marks }}@if (! $loop->last)<br>@endif @endforeach</td>
                                <td class="text-end fw-bold">{{ $l->interviewScore() ?? '–' }}</td>
                            @else
                                <td class="text-end">{{ $l->marks->count() }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted">No candidates for this date.</td></tr>
                    @endforelse
                </table>
            </div>
            @if ($letters->isNotEmpty() && ! $locked)<button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-save me-1"></i>Save my marks</button>@endif
        </form>
    @endif
</div></div>
@endsection
