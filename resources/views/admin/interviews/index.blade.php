@extends('layouts.admin')
@section('title', 'Interview Call Letters')
@section('content')
<div class="card"><div class="card-body">
    <a href="{{ route('admin.interviews.summary') }}" class="btn btn-primary btn-sm float-end ms-3"><i class="fas fa-chart-bar me-1"></i>Attendance summary</a>
    <p class="small text-muted">Candidates become eligible for a course's interview when their fee is paid, academic scrutiny for that course is approved and (for Christian applicants) religious scrutiny is decided. Finalise marks once interviews are over; only finalised courses go into seat allotment.</p>
    <div class="table-responsive">
        <table class="table table-sm table-compact align-middle">
            <thead><tr><th>Course</th><th class="text-end">Eligible</th><th class="text-end">Letters issued</th><th class="text-end">Present</th><th class="text-end">Absent</th><th>Marks</th><th></th></tr></thead>
            <tbody>
            @foreach ($courses as $c)
                @php $n = $counts[$c->id] ?? null; @endphp
                <tr>
                    <td>{{ $c->name }}</td>
                    <td class="text-end">{{ $eligible[$c->id] }}</td>
                    <td class="text-end">{{ $n->issued ?? 0 }}</td>
                    <td class="text-end">{{ $n->present ?? 0 }}</td>
                    <td class="text-end">{{ $n->absent ?? 0 }}</td>
                    <td>@if (in_array($c->id, $locked, true))<span class="badge bg-success">Finalised</span>@else<span class="badge bg-secondary">Open</span>@endif</td>
                    <td class="text-end"><a href="{{ route('admin.interviews.course', $c) }}" class="btn btn-outline-primary btn-sm">Manage</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>
@endsection
