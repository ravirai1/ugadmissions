@extends('layouts.admin')
@section('title', 'Interview Attendance Summary')
@section('content')
@php $pct = fn ($a, $b) => $b ? round($a / $b * 100) : 0; @endphp
<div class="row">
    @foreach ([['Call letters issued', $totals['issued'], 'primary'], ['Present', $totals['present'], 'success'], ['Absent', $totals['absent'], 'danger'], ['Not marked yet', $totals['unmarked'], 'warning'], ['Marks entered', $totals['marked'], 'info']] as [$label, $n, $c])
        <div class="col-6 col-md"><div class="card card-stats"><div class="card-body">
            <p class="card-category mb-1">{{ $label }}</p>
            <h3 class="card-title mb-0 text-{{ $c }}">{{ $n }}</h3>
            @if ($label !== 'Call letters issued')<span class="small text-muted">{{ $pct($n, $totals['issued']) }}% of letters</span>@endif
        </div></div></div>
    @endforeach
</div>

<div class="card"><div class="card-body">
    <h5 class="card-title">By course</h5>
    <div class="table-responsive"><table class="table table-sm table-compact">
        <thead><tr><th>Course</th><th class="text-end">Letters</th><th class="text-end">Present</th><th class="text-end">Absent</th><th class="text-end">Not marked</th><th class="text-end">Marks entered</th><th class="text-end">Turnout</th></tr></thead>
        @forelse ($byCourse as $cid => $r)
            <tr>
                <td><a href="{{ route('admin.interviews.course', $cid) }}">{{ $courses[$cid] ?? $cid }}</a></td>
                <td class="text-end">{{ $r['issued'] }}</td>
                <td class="text-end text-success">{{ $r['present'] }}</td>
                <td class="text-end text-danger">{{ $r['absent'] }}</td>
                <td class="text-end">{{ $r['unmarked'] }}</td>
                <td class="text-end">{{ $r['marked'] }}</td>
                <td class="text-end">{{ $pct($r['present'], $r['present'] + $r['absent']) }}%</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-muted">No call letters issued yet.</td></tr>
        @endforelse
    </table></div>
</div></div>

<div class="card"><div class="card-body">
    <h5 class="card-title">By date</h5>
    @forelse ($byDate as $date => $rows)
        <h6 class="mt-3">{{ \Illuminate\Support\Carbon::parse($date)->format('l, d M Y') }}
            <span class="small text-muted">· {{ $rows->sum('issued') }} letters · {{ $rows->sum('present') }} present · {{ $rows->sum('absent') }} absent · {{ $rows->sum('unmarked') }} not marked</span>
            <a class="small ms-2" href="{{ route('admin.attendance.index', ['date' => $date]) }}">Attendance list</a>
        </h6>
        <table class="table table-sm table-compact small mb-2">
            <thead><tr><th>Course</th><th class="text-end">Letters</th><th class="text-end">Present</th><th class="text-end">Absent</th><th class="text-end">Not marked</th><th class="text-end">Marks entered</th></tr></thead>
            @foreach ($rows as $r)
                <tr><td>{{ $courses[$r->course_id] ?? $r->course_id }}</td><td class="text-end">{{ $r->issued }}</td><td class="text-end text-success">{{ $r->present }}</td><td class="text-end text-danger">{{ $r->absent }}</td><td class="text-end">{{ $r->unmarked }}</td><td class="text-end">{{ $r->marked }}</td></tr>
            @endforeach
        </table>
    @empty
        <p class="text-muted small mb-0">No interviews scheduled.</p>
    @endforelse
</div></div>
@endsection
