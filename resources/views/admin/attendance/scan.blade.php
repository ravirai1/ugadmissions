@extends('layouts.admin')
@section('title', 'Call Letter '.$letter->letter_no)
@section('content')
@php $app = $letter->application; @endphp
<div class="card" style="max-width: 640px"><div class="card-body">
    <div class="d-flex gap-3">
        <div class="text-center">
            @if ($photo)<img src="{{ route('admin.attendance.image', [$letter, 'photo']) }}" alt="Photo" style="width:120px;height:145px;object-fit:cover;border:1px solid #ccc">@endif
            @if ($signature)<img src="{{ route('admin.attendance.image', [$letter, 'signature']) }}" alt="Signature" class="d-block mt-2" style="width:120px;height:45px;object-fit:contain;border:1px solid #eee">@endif
        </div>
        <div>
            <h4 class="mb-1">{{ $app->user->name }}</h4>
            <p class="mb-1">{{ $app->application_no }} · CUET {{ $app->user->cuet_no }}</p>
            <p class="mb-1"><strong>{{ $letter->course->name }}</strong></p>
            <table class="table table-sm small mb-2">
                <tr><th class="pe-3">Date of birth</th><td>{{ $app->dob?->format('d M Y') }} · {{ $app->gender }}</td></tr>
                <tr><th class="pe-3">Father</th><td>{{ \App\Support\PersonalDetails::withTitle($app->father_title, $app->father_name) }}</td></tr>
                <tr><th class="pe-3">Mother</th><td>{{ \App\Support\PersonalDetails::withTitle($app->mother_title, $app->mother_name) }}</td></tr>
                <tr><th class="pe-3">Mobile</th><td>+91 {{ $app->user->mobile }}</td></tr>
                <tr><th class="pe-3">Category</th><td>{{ $app->meritCategory() }}{{ $app->is_pwbd ? ' · PwBD' : '' }}{{ $app->needs_scribe ? ' (needs scribe / extra time)' : '' }}</td></tr>
                <tr><th class="pe-3">State</th><td>{{ $app->domicileState?->name }}</td></tr>
            </table>
            <p class="mb-1">{{ $letter->interview_date->format('l, d M Y') }}, {{ $letter->reporting_time }}</p>
            @if (! $letter->interview_date->isSameDay(now('Asia/Kolkata')))
                <div class="alert alert-warning py-1 px-2 small mb-1">This interview is not today.</div>
            @endif
            <p class="small text-muted mb-0">Status: <strong>{{ $letter->attendance ? ucfirst($letter->attendance) : 'Not marked' }}</strong>@if ($letter->attendance_at) at {{ $letter->attendance_at->timezone('Asia/Kolkata')->format('d M, h:i A') }} by {{ $letter->markedBy?->name }}@endif</p>
        </div>
    </div>
    <hr>
    <div class="d-flex gap-2">@include('admin.attendance._buttons', ['l' => $letter])</div>
</div></div>
<a href="{{ route('admin.attendance.index', ['date' => $letter->interview_date->toDateString()]) }}" class="btn btn-outline-secondary btn-sm">Back to the list</a>
@endsection
