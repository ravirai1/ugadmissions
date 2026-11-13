@extends('application._layout')
@section('step')
@php
    $b = $app->baptism_details ?? [];
    $addr = fn ($p) => collect([$app->{$p.'_line1'}, $app->{$p.'_line2'}, $app->{$p.'_city'}, $app->{$p.'District'}?->name ?? $app->{$p.'_district_other'}, $app->{$p.'State'}?->name, $app->{$p.'_pin'}])->filter()->implode(', ');
@endphp
@if ($app->isSubmitted())
    <div class="alert alert-success">Application <strong>{{ $app->application_no }}</strong> was submitted on {{ $app->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}. It can no longer be edited.</div>
@elseif ($missing)
    <div class="alert alert-warning"><strong>Before you can submit:</strong><ul class="mb-0">@foreach ($missing as $m)<li>{{ $m }}</li>@endforeach</ul></div>
@endif

<div class="form-section">
    <div class="d-flex justify-content-between">
        <h5>Basic details</h5>
        @if (isset($uploaded['photo']))<img src="{{ route('application.document', $uploaded['photo']) }}" alt="Photo" class="doc-thumb">@endif
    </div>
    <table class="table table-sm table-bordered preview-table mb-0">
        <tr><th>Name</th><td>{{ $user->name }}</td></tr>
        <tr><th>CUET application no.</th><td>{{ $user->cuet_no }}</td></tr>
        <tr><th>Email / mobile</th><td>{{ $user->email }} / +91 {{ $user->mobile }}</td></tr>
        <tr><th>Date of birth</th><td>{{ $app->dob?->format('d M Y') }}</td></tr>
        <tr><th>Gender</th><td>{{ $app->gender }}</td></tr>
        <tr><th>Father / mother</th><td>{{ $app->father_name }} / {{ $app->mother_name }}</td></tr>
        <tr><th>Annual family income</th><td>{{ $incomes[$app->family_income] ?? '' }}</td></tr>
        <tr><th>Last school</th><td>{{ $app->last_school }}, {{ $app->last_school_city }}</td></tr>
    </table>
</div>

<div class="form-section">
    <h5>Address</h5>
    <table class="table table-sm table-bordered preview-table mb-0">
        <tr><th>Current address</th><td>{{ $addr('cur') }}</td></tr>
        <tr><th>Permanent address</th><td>{{ $app->perm_same ? 'Same as current address' : $addr('perm') }}</td></tr>
        <tr><th>State you belong to</th><td>{{ $app->domicileState?->name }}</td></tr>
    </table>
</div>

<div class="form-section">
    <h5>Category</h5>
    <table class="table table-sm table-bordered preview-table mb-0">
        <tr><th>Category</th><td>{{ $categoryNames[$app->category_code] ?? '' }}@if ($app->christian_subcategory) – {{ $categoryNames[$app->christian_subcategory] ?? '' }}@endif @if ($app->is_cst) (CST) @endif</td></tr>
        @if ($app->isChristian())
            <tr><th>Baptism</th><td>{{ $b['baptism_date'] ?? '' }}, {{ $b['baptism_church'] ?? '' }}, {{ $b['baptism_place'] ?? '' }}</td></tr>
            <tr><th>Baptised by</th><td>{{ $b['baptism_priest_designation'] ?? '' }} {{ $b['baptism_priest'] ?? '' }}</td></tr>
            <tr><th>Denomination</th><td>{{ $b['denomination'] ?? '' }}</td></tr>
            <tr><th>Present church / priest</th><td>{{ $b['current_church'] ?? '' }} / {{ $b['current_priest'] ?? '' }}</td></tr>
        @endif
        <tr><th>PwBD</th><td>@if ($app->is_pwbd){{ $app->pwbdType?->name }}, {{ $app->pwbd_percentage }}%, UDID {{ $app->udid_no }}{{ $app->needs_scribe ? ', needs scribe/extra time' : '' }}@else No @endif</td></tr>
    </table>
</div>

<div class="form-section">
    <h5>Class 10 &amp; 12</h5>
    <table class="table table-sm table-bordered preview-table">
        <tr><th>Class 10</th><td>{{ $app->c10_board }}, {{ $app->c10_year }}, roll {{ $app->c10_roll_no }} – {{ $app->c10_obtained + 0 }}/{{ $app->c10_max }} ({{ $app->class10Percentage() }}%)</td></tr>
        <tr><th>Class 12</th><td>{{ $app->c12_board }}, {{ $app->c12_year }}, roll {{ $app->c12_roll_no }} – aggregate {{ $app->class12Percentage() }}%</td></tr>
    </table>
    <table class="table table-sm">
        <thead><tr><th>Subject</th><th>Max</th><th>Obtained</th></tr></thead>
        @foreach ($app->class12Marks as $m)<tr><td>{{ $m->class12Subject->name }}</td><td>{{ $m->max_marks }}</td><td>{{ $m->marks_obtained + 0 }}</td></tr>@endforeach
    </table>
</div>

<div class="form-section">
    <h5>CUET (UG) 2027</h5>
    <p class="small mb-2">Roll no. {{ $app->cuet_roll_no }}</p>
    <table class="table table-sm">
        <thead><tr><th>Paper</th><th>Score</th></tr></thead>
        @foreach ($app->cuetScores as $s)<tr><td>{{ $s->subject->name }}</td><td>{{ $s->score }}</td></tr>@endforeach
    </table>
</div>

<div class="form-section">
    <h5>Courses</h5>
    <p class="small mb-2">DU CSAS application no. {{ $app->csas_application_no }} · Residence: <strong>{{ $app->residence ? 'Yes' : 'No' }}</strong></p>
    <table class="table table-sm">
        <thead><tr><th>DU pref.</th><th>Course</th><th>Papers counted</th><th>CUET total</th>@if ($app->isFemale())<th>JMC pref.</th>@endif</tr></thead>
        @foreach ($app->courses as $c)
            <tr>
                <td>{{ $c->du_preference }}</td>
                <td>{{ $c->course->name }}@if ($c->combination)<br><small class="text-muted">{{ $c->combination }}</small>@endif</td>
                <td class="small">{{ collect($c->papers)->map(fn ($id) => $subjects[$id]->name ?? '')->implode(', ') }}</td>
                <td>{{ number_format($c->cuet_score, 2) }}</td>
                @if ($app->isFemale())<td>{{ $c->course->offered_at_jmc ? ($c->jmc_not_applied ? 'Not applied' : $c->jmc_preference) : '–' }}</td>@endif
            </tr>
        @endforeach
    </table>
</div>

<div class="form-section">
    <h5>Statements</h5>
    <p class="fw-bold mb-1">Why St. Stephen's College?</p>
    <p style="white-space: pre-line">{{ $app->statement_college }}</p>
    @foreach ($app->courses as $c)
        <p class="fw-bold mb-1">Why {{ $c->course->name }}?</p>
        <p style="white-space: pre-line">{{ $c->statement }}</p>
    @endforeach
</div>

<div class="form-section">
    <h5>Documents</h5>
    <ul class="mb-0">
        @foreach ($required as $type)
            <li>{{ \App\Support\DocumentTypes::label($type) }}:
                @if (isset($uploaded[$type]))<a href="{{ route('application.document', $uploaded[$type]) }}" target="_blank">view</a> <i class="fa fa-check text-success"></i>@else <span class="text-danger">missing</span>@endif
            </li>
        @endforeach
    </ul>
</div>

@unless ($app->isSubmitted())
    <form method="POST" action="{{ route('application.submit') }}" class="form-section">
        @csrf
        <div class="form-check mb-3">
            <input class="form-check-input @error('declaration') is-invalid @enderror" type="checkbox" id="declaration" name="declaration" value="1">
            <label class="form-check-label" for="declaration">I declare that the information given in this application is true and complete. I understand that the form cannot be edited after submission and that false information will lead to cancellation of my application or admission.</label>
        </div>
        <div class="d-flex justify-content-between">
            <a href="{{ route('application.step', 'documents') }}" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i>Back</a>
            <button type="submit" class="btn btn-primary px-4" @disabled($missing)>Final Submit</button>
        </div>
    </form>
@endunless
@endsection
