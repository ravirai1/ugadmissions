@extends('layouts.admin')
@section('title', 'Attendance Desk')
@section('content')
<div class="card"><div class="card-body">
    <p class="small text-muted">Scan the QR code on the candidate's call letter with your phone camera (while logged in here), or search by call letter or application number.</p>
    <form class="row g-2 mb-3" method="GET">
        <div class="col-md-3"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Call letter or application no."></div>
        <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="date" value="{{ $date->toDateString() }}" aria-label="Interview date"></div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" name="course" aria-label="Course">
                <option value="">All courses</option>
                @foreach ($courses as $id => $name)<option value="{{ $id }}" @selected((string) request('course') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary btn-sm" type="submit">Show</button></div>
    </form>
    <p class="small mb-2">Candidates: <strong>{{ $summary['total'] }}</strong> · Present: <strong class="text-success">{{ $summary['present'] }}</strong> · Absent: <strong class="text-danger">{{ $summary['absent'] }}</strong> · Not marked: <strong>{{ $summary['total'] - $summary['present'] - $summary['absent'] }}</strong></p>
    <div class="table-responsive">
        <table class="table table-sm table-compact align-middle">
            <thead><tr><th></th><th>Letter</th><th>Name</th><th>Application</th><th>Course</th><th>Date</th><th>Marked</th><th class="text-end">Attendance</th></tr></thead>
            @forelse ($letters as $l)
                <tr>
                    <td>@if ($l->application->documents->firstWhere('type', 'photo'))<img src="{{ route('admin.attendance.image', [$l, 'photo']) }}" alt="" style="width:36px;height:44px;object-fit:cover" loading="lazy">@endif</td>
                    <td><a href="{{ route('admin.attendance.scan', $l->qr_token) }}">{{ $l->letter_no }}</a></td>
                    <td>{{ $l->application->user->name }}<br><a class="small" href="{{ route('admin.attendance.scan', $l->qr_token) }}">Details</a></td>
                    <td>{{ $l->application->application_no }}</td>
                    <td class="small">{{ $l->course->name }}</td>
                    <td class="small">{{ $l->interview_date->format('d M') }}, {{ $l->reporting_time }}</td>
                    <td class="small text-muted">{{ $l->attendance_at?->timezone('Asia/Kolkata')->format('h:i A') }}</td>
                    <td class="text-end text-nowrap">@include('admin.attendance._buttons')</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-muted">No candidates for this day.</td></tr>
            @endforelse
        </table>
    </div>
</div></div>
@endsection
