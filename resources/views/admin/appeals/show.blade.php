@extends('layouts.admin')
@section('title', 'Appeal – '.$a->application->application_no)
@section('content')
@php($s = $a->scrutiny)
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-1">{{ $a->application->user->name }} · {{ $a->application->application_no }}</h5>
                <p class="small text-muted mb-3">{{ $s->type === 'religious' ? 'Religious scrutiny (Christian category)' : 'Academic scrutiny: '.$s->applicationCourse?->course->name }} ·
                    <a href="{{ route('admin.scrutiny.show', $s) }}">Open scrutiny record</a> · <a href="{{ route('admin.applications.show', $a->application) }}">Full application</a></p>

                <h6>Rejection being appealed</h6>
                <p class="small text-muted mb-1">Rejected {{ $s->reviewed_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}@if ($s->reviewer) by {{ $s->reviewer->name }}@endif</p>
                <div class="border rounded p-2 mb-3" style="white-space: pre-line">{{ $a->rejection_remarks ?: 'No remarks recorded.' }}</div>
                @if ($s->review_comment)
                    <p class="small mb-3"><strong>Comment when marked for review</strong>@if ($s->reviewFlaggedBy) ({{ $s->reviewFlaggedBy->name }})@endif: {{ $s->review_comment }}</p>
                @endif

                <h6>Applicant's appeal</h6>
                <p class="small text-muted mb-1">Received {{ $a->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</p>
                <div class="border rounded p-2 mb-3" style="white-space: pre-line">{{ $a->reason }}</div>
                @if ($a->attachments->isNotEmpty())
                    <p class="small mb-1">Documents attached:</p>
                    <ul class="small">@foreach ($a->attachments as $d)<li><a href="{{ route('admin.applications.document', $d) }}" target="_blank" rel="noopener">{{ $d->original_name }}</a> <span class="text-muted">({{ $d->stored_name }})</span></li>@endforeach</ul>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Decision</h5>
                @if ($canDecide)
                    @if ($errors->any())<div class="alert alert-danger small">@foreach ($errors->all() as $e){{ $e }}<br>@endforeach</div>@endif
                    <form method="POST" action="{{ route('admin.appeals.decide', $a) }}">
                        @csrf
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="outcome" id="o-allowed" value="allowed" required>
                            <label class="form-check-label" for="o-allowed" style="white-space: normal"><strong>Accept the appeal</strong>: set the rejection aside and send the record back for scrutiny</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="outcome" id="o-upheld" value="upheld">
                            <label class="form-check-label" for="o-upheld" style="white-space: normal"><strong>Reject the appeal</strong>: the rejection stands (final)</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="remarks">Remarks (the applicant sees these) <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="4" minlength="10" maxlength="2000" required>{{ old('remarks') }}</textarea>
                        </div>
                        <button class="btn btn-primary">Save decision</button>
                    </form>
                @else
                    <p><span class="badge {{ ['pending' => 'bg-warning text-dark', 'allowed' => 'bg-success', 'upheld' => 'bg-secondary'][$a->status] }}">{{ $a->statusLabel() }}</span></p>
                    @if ($a->decided_at)
                        <p class="small text-muted mb-1">{{ $a->decided_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }} by {{ $a->decidedBy?->name }}</p>
                        <div class="border rounded p-2" style="white-space: pre-line">{{ $a->decision_remarks }}</div>
                    @else
                        <p class="small text-muted mb-0">Waiting for the TFA admin's decision.</p>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
