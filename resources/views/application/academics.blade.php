@extends('application._layout')
@section('step')
@php
    $rows = old('marks', $marks->map(fn ($m) => ['subject' => $m->class12_subject_id, 'max' => $m->max_marks, 'obtained' => $m->marks_obtained + 0])->values()->all());
    if (! $rows) { $rows = array_fill(0, 5, ['subject' => '', 'max' => 100, 'obtained' => '']); }
@endphp
<form method="POST" action="{{ route('application.save', $step) }}" data-app-form novalidate>
    @csrf
    <div class="form-section">
        <h5>Class 10</h5>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Board <span class="req">*</span></label><input class="form-control @error('c10_board') is-invalid @enderror" name="c10_board" maxlength="100" placeholder="e.g. CBSE, CISCE, State Board" value="{{ old('c10_board', $app->c10_board) }}"></div>
            <div class="col-md-2"><label class="form-label">Year <span class="req">*</span></label><input type="number" class="form-control @error('c10_year') is-invalid @enderror" name="c10_year" value="{{ old('c10_year', $app->c10_year) }}"></div>
            <div class="col-md-2"><label class="form-label">Roll no. <span class="req">*</span></label><input class="form-control @error('c10_roll_no') is-invalid @enderror" name="c10_roll_no" maxlength="30" value="{{ old('c10_roll_no', $app->c10_roll_no) }}"></div>
            <div class="col-md-2"><label class="form-label">Max marks <span class="req">*</span></label><input type="number" class="form-control @error('c10_max') is-invalid @enderror" name="c10_max" value="{{ old('c10_max', $app->c10_max ?? 500) }}"></div>
            <div class="col-md-2"><label class="form-label">Obtained <span class="req">*</span></label><input type="number" step="0.01" class="form-control @error('c10_obtained') is-invalid @enderror" name="c10_obtained" value="{{ old('c10_obtained', $app->c10_obtained) }}"></div>
        </div>
    </div>
    <div class="form-section">
        <h5>Class 12</h5>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label">Board <span class="req">*</span></label><input class="form-control @error('c12_board') is-invalid @enderror" name="c12_board" maxlength="100" value="{{ old('c12_board', $app->c12_board) }}"></div>
            <div class="col-md-3"><label class="form-label">Year <span class="req">*</span></label><input type="number" class="form-control @error('c12_year') is-invalid @enderror" name="c12_year" value="{{ old('c12_year', $app->c12_year ?? date('Y') + 1) }}"></div>
            <div class="col-md-3"><label class="form-label">Roll no. <span class="req">*</span></label><input class="form-control @error('c12_roll_no') is-invalid @enderror" name="c12_roll_no" maxlength="30" value="{{ old('c12_roll_no', $app->c12_roll_no) }}"></div>
        </div>
        <p class="small text-muted">Choose each subject exactly as it appears on your marksheet. CUET papers you enter in the next step must match these subjects.</p>
        <div data-repeater data-max="8">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th style="width:50%">Subject</th><th>Max marks</th><th>Marks obtained</th><th></th></tr></thead>
                    <tbody data-rows>
                        @foreach ($rows as $i => $r)
                            @include('application._mark-row', ['i' => $i, 'r' => $r])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template>@include('application._mark-row', ['i' => '__INDEX__', 'r' => ['subject' => '', 'max' => 100, 'obtained' => '']])</template>
            <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-row><i class="fa fa-plus me-1"></i>Add subject</button>
                <div>Aggregate: <strong data-percentage>–</strong></div>
            </div>
        </div>
    </div>
    @include('application._buttons')
</form>
@endsection
