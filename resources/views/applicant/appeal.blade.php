@extends('layouts.frontend')
@section('title', 'Appeal against scrutiny decision')
@section('page_title', 'Appeal Against Scrutiny Decision')
@section('content')
@php
    $what = $s->type === 'religious' ? 'Religious scrutiny (Christian category)' : 'Academic scrutiny: '.$s->applicationCourse?->course->name;
@endphp
<div class="container" style="max-width: 800px">
    <p class="mb-3"><a href="{{ route('applicant.dashboard') }}">Back to dashboard</a></p>
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="p-4 border mb-4">
        <p class="small text-muted mb-1">Application {{ $s->application->application_no }} · {{ $what }}</p>
        <h5 class="mb-2">Decision being appealed</h5>
        <p class="mb-1"><span class="badge bg-danger">Rejected</span> on {{ $s->reviewed_at?->timezone('Asia/Kolkata')->format('d M Y') }}</p>
        <p style="white-space: pre-line" class="mb-0">{{ $appeal?->rejection_remarks ?? $s->remarks ?? 'No reason was recorded.' }}</p>
    </div>

    @if ($appeal)
        <div class="p-4 border mb-4">
            <h5 class="mb-2">Your appeal</h5>
            <p class="small text-muted mb-2">Sent {{ $appeal->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</p>
            <p style="white-space: pre-line">{{ $appeal->reason }}</p>
            @if ($appeal->attachments->isNotEmpty())
                <p class="small mb-1">Documents attached:</p>
                <ul class="small">@foreach ($appeal->attachments as $d)<li><a href="{{ route('application.document', $d) }}" target="_blank" rel="noopener">{{ $d->original_name }}</a></li>@endforeach</ul>
            @endif
        </div>
        <div class="alert {{ ['pending' => 'alert-warning', 'upheld' => 'alert-secondary', 'allowed' => 'alert-success'][$appeal->status] }}">
            <strong>{{ $appeal->statusLabel() }}</strong>
            @if ($appeal->decided_at)
                · decided {{ $appeal->decided_at->timezone('Asia/Kolkata')->format('d M Y') }}
                <div class="mt-1" style="white-space: pre-line">{{ $appeal->decision_remarks }}</div>
                @if ($appeal->status === 'upheld')<div class="small mt-1">This decision is final.</div>@endif
            @else
                <div class="small mt-1">The TFA admin will review your appeal. You will be emailed when it is decided.</div>
            @endif
        </div>
    @else
        <div class="alert alert-info small">
            You can appeal <strong>once</strong>, until <strong>{{ $s->appealDeadline()->timezone('Asia/Kolkata')->format('d M Y, h:i A') }} IST</strong>. Explain clearly why the decision is wrong and attach any document that shows it (for example a clearer marksheet or certificate). The TFA admin's decision on the appeal is final.
        </div>
        <form method="POST" action="{{ route('applicant.appeal.store', $s) }}" enctype="multipart/form-data" class="p-4 border">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="reason">Why should the decision be reviewed? <span class="req">*</span></label>
                <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" rows="6" minlength="50" maxlength="3000" required>{{ old('reason') }}</textarea>
                <div class="form-text">At least 50 characters.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="files">Supporting documents (optional, up to 3)</label>
                <input type="file" class="form-control @error('files') is-invalid @enderror" id="files" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf">
                <div class="form-text">JPG, PNG or PDF, up to 2 MB each.</div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="declaration" name="declaration" value="1" required>
                <label class="form-check-label small" for="declaration">I declare that the information and documents I am giving are true. I understand that a false statement will lead to cancellation of my application or admission.</label>
            </div>
            <button type="submit" class="btn btn-primary px-4">Submit appeal</button>
        </form>
    @endif
</div>
@endsection
