@extends('application._layout')
@section('step')
@php
    $covered = $class12Names->pluck('class12Subject.subject_id')->filter()->unique()->all();
    $rows = old('scores', $scores->map(fn ($s) => ['subject' => $s->subject_id, 'score' => $s->score + 0])->values()->all());
    if (! $rows) { $rows = array_fill(0, 4, ['subject' => '', 'score' => '']); }
    $typeLabels = ['language' => 'Section I – Languages', 'domain' => 'Section II – Domain subjects', 'general' => 'Section III – General Test'];
@endphp
<form method="POST" action="{{ route('application.save', $step) }}" data-app-form novalidate>
    @csrf
    <div class="form-section">
        <h5>CUET (UG) 2027 details</h5>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">CUET application no.</label><input class="form-control" value="{{ $user->cuet_no }}" disabled></div>
            <div class="col-md-4"><label class="form-label">CUET roll no. <span class="req">*</span></label><input class="form-control @error('cuet_roll_no') is-invalid @enderror" name="cuet_roll_no" maxlength="20" value="{{ old('cuet_roll_no', $app->cuet_roll_no) }}"></div>
        </div>
    </div>
    <div class="form-section">
        <h5>Papers appeared and scores</h5>
        <p class="small text-muted">Enter each paper and the normalised score exactly as on your NTA scorecard. Every domain paper must match a subject you studied in Class 12.</p>
        <div data-repeater data-max="7">
            <table class="table align-middle">
                <thead><tr><th style="width:55%">CUET paper</th><th>Score</th><th>Class 12 match</th><th></th></tr></thead>
                <tbody data-rows>
                    @foreach ($rows as $i => $r)
                        @include('application._cuet-row', ['i' => $i, 'r' => $r])
                    @endforeach
                </tbody>
            </table>
            <template>@include('application._cuet-row', ['i' => '__INDEX__', 'r' => ['subject' => '', 'score' => '']])</template>
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-row><i class="fa fa-plus me-1"></i>Add paper</button>
        </div>
    </div>
    <div class="form-section">
        <h5>Your Class 12 subjects</h5>
        <ul class="mb-0 small">
            @foreach ($class12Names as $m)
                <li>{{ $m->class12Subject->name }} → {{ $m->class12Subject->subject?->name ?? 'no CUET paper' }}</li>
            @endforeach
        </ul>
    </div>
    @include('application._buttons')
</form>
@endsection
