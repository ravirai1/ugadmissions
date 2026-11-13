@extends('layouts.admin')
@section('title', 'Call Letter '.$letter->letter_no)
@section('content')
@php $app = $letter->application; @endphp
<div class="card" style="max-width: 640px"><div class="card-body">
    <div class="d-flex gap-3">
        @if ($photo)<img src="{{ route('admin.applications.document', $photo) }}" alt="Photo" style="width:120px;height:145px;object-fit:cover;border:1px solid #ccc">@endif
        <div>
            <h4 class="mb-1">{{ $app->user->name }}</h4>
            <p class="mb-1">{{ $app->application_no }} · CUET {{ $app->user->cuet_no }}</p>
            <p class="mb-1"><strong>{{ $letter->course->name }}</strong></p>
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
