{{-- Full application details. Needs $app, $user, $incomes, $categoryNames, $subjects, $uploaded, $required and $docUrl (document => URL). --}}
@php
    $b = $app->baptism_details ?? [];
    $addr = fn ($p) => collect([$app->{$p.'_line1'}, $app->{$p.'_line2'}, $app->{$p.'_city'}, $app->{$p.'District'}?->name ?? $app->{$p.'_district_other'}, $app->{$p.'State'}?->name, $app->{$p.'_pin'}])->filter()->implode(', ');
@endphp
<div class="form-section">
    <div class="d-flex justify-content-between">
        <h5>Basic details</h5>
        @if (isset($uploaded['photo']))<img src="{{ $docUrl($uploaded['photo']) }}" alt="Photo" class="doc-thumb">@endif
    </div>
    <table class="table table-sm table-bordered preview-table mb-0">
        <tr><th>Name</th><td>{{ $user->name }}</td></tr>
        <tr><th>CUET application no.</th><td>{{ $user->cuet_no }}</td></tr>
        <tr><th>Email / mobile</th><td>{{ $user->email }} / +91 {{ $user->mobile }}</td></tr>
        <tr><th>Date of birth</th><td>{{ $app->dob?->format('d M Y') }}</td></tr>
        <tr><th>Gender</th><td>{{ $app->gender }}</td></tr>
        <tr><th>Blood group</th><td>{{ $app->blood_group }}</td></tr>
        <tr><th>Nationality</th><td>{{ $app->nationality }}</td></tr>
        <tr><th>Mother tongue</th><td>{{ $app->mother_tongue === 'Other' ? $app->mother_tongue_other : $app->mother_tongue }}</td></tr>
        @foreach (['father' => 'Father', 'mother' => 'Mother'] as $p => $label)
            <tr><th>{{ $label }}</th><td>
                <strong>{{ \App\Support\PersonalDetails::withTitle($app->{$p.'_title'}, $app->{$p.'_name'}) }}</strong>
                @if ($app->{$p.'_qualification'})<br>Qualification: {{ $app->{$p.'_qualification'} }}@endif
                @if ($app->{$p.'_occupation'})<br>Occupation: {{ $app->{$p.'_occupation'} }}@endif
                @if ($app->{$p.'_designation'})<br>{{ $app->{$p.'_designation'} }}, {{ $app->{$p.'_organisation'} }}@endif
                @if ($app->{$p.'_office_address'})<br>Office: {{ $app->{$p.'_office_address'} }}@endif
                @if ($app->{$p.'_mobile'} || $app->{$p.'_email'})<br>{{ collect([$app->{$p.'_mobile'} ? '+91 '.$app->{$p.'_mobile'} : null, $app->{$p.'_email'}])->filter()->implode(' / ') }}@endif
            </td></tr>
        @endforeach
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
            @include('application._baptism-rows')
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
                @if (isset($uploaded[$type]))<a href="{{ $docUrl($uploaded[$type]) }}" target="_blank" rel="noopener">view</a> <i class="fa fa-check text-success"></i>@else <span class="text-danger">missing</span>@endif
            </li>
        @endforeach
    </ul>
</div>

