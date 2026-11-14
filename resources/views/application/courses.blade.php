@extends('application._layout')
@section('step')
@php
    $female = $app->isFemale();
@endphp
<form method="POST" action="{{ route('application.save', $step) }}" data-app-form novalidate>
    @csrf
    @if ($reopened)
        <div class="alert alert-info"><strong>{{ \App\Support\AdmissionWindow::status() }}</strong> Only the courses with vacant seats are listed below.</div>
    @endif
    <div class="du-alert mb-4" role="alert">
        <h5 class="mb-2"><i class="fa fa-exclamation-triangle me-2"></i>You must apply on the DU CSAS (UG) portal as well</h5>
        <p class="mb-2"><strong>Choose the SAME courses at St. Stephen's College on the DU CSAS (UG) portal, and enter here the SAME preference number you gave each one there.</strong></p>
        <p class="mb-2">If a course is not chosen at St. Stephen's College on the DU portal, or the preference number does not match, <strong>you will not be eligible for that course</strong>, even if you apply here.</p>
        <a href="{{ config('admissions.du_csas_url') }}" target="_blank" rel="noopener" class="btn btn-sm btn-dark"><i class="fa fa-external-link-alt me-1"></i>Open the DU CSAS (UG) portal</a>
    </div>
    <div class="form-section">
        <h5>DU CSAS (UG) details</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="csas_application_no">DU CSAS (UG) application no. <span class="req">*</span></label>
                <input class="form-control @error('csas_application_no') is-invalid @enderror" id="csas_application_no" name="csas_application_no" maxlength="30" value="{{ old('csas_application_no', $app->csas_application_no) }}">
            </div>
        </div>
        <p class="small text-muted mt-2 mb-0">For each course, enter the preference number you gave this programme at St. Stephen's College in your DU CSAS form.@if ($female) For courses also offered at Jesus and Mary College (JMC), also enter your JMC preference number.@endif</p>
    </div>

    <div class="form-section">
        <h5>Choose courses</h5>
        <p class="small text-muted">Only courses you are eligible for under DU rules (based on your Class 12 subjects and CUET papers) can be selected. @php $fees = \App\Services\FeeCalculator::fees(); @endphp Fee: ₹{{ $fees['first_course'] }} for the first course and ₹{{ $fees['additional_course'] }} for each additional course.</p>
        @error('courses')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

        @foreach ($courses as $course)
            @include('application._course-card', ['e' => $evals[$course->id], 'c' => $chosen[$course->id] ?? null])
        @endforeach
    </div>

    <div class="form-section">
        <h5>Residence</h5>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="residence" name="residence" value="1" @checked(old('residence', $app->residence))>
            <label class="form-check-label" for="residence">I wish to apply for College residence (additional ₹{{ number_format(\App\Services\FeeCalculator::fees()['residence']) }})</label>
        </div>
    </div>
    <div class="du-alert du-alert-sm mb-3">
        <div class="form-check mb-0">
            <input class="form-check-input @error('du_csas_confirm') is-invalid @enderror" type="checkbox" name="du_csas_confirm" id="du_csas_confirm" value="1" @checked(old('du_csas_confirm', $app->csas_application_no))>
            <label class="form-check-label fw-bold" for="du_csas_confirm">I have chosen the same courses at St. Stephen's College on the DU CSAS (UG) portal, and the preference numbers above are the same as there. <span class="req">*</span></label>
            @error('du_csas_confirm')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    @include('application._buttons')
</form>
@endsection
