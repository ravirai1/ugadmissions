@extends('application._layout')
@section('step')
@php
    $female = $app->isFemale();
@endphp
<form method="POST" action="{{ route('application.save', $step) }}" data-app-form novalidate>
    @csrf
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
        <p class="small text-muted">Only courses you are eligible for under DU rules (based on your Class 12 subjects and CUET papers) can be selected. Fee: ₹500 for the first course and ₹200 for each additional course.</p>
        @error('courses')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

        @foreach ($courses as $course)
            @include('application._course-card', ['e' => $evals[$course->id], 'c' => $chosen[$course->id] ?? null])
        @endforeach
    </div>

    <div class="form-section">
        <h5>Residence</h5>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="residence" name="residence" value="1" @checked(old('residence', $app->residence))>
            <label class="form-check-label" for="residence">I wish to apply for College residence (additional ₹1,000)</label>
        </div>
    </div>
    @include('application._buttons')
</form>
@endsection
