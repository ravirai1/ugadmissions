@extends('application._layout')
@section('step')
<form method="POST" action="{{ route('application.save', $step) }}" data-app-form novalidate>
    @csrf
    <div class="form-section">
        <h5>Why do you want to study at St. Stephen's College? <span class="req">*</span></h5>
        <textarea class="form-control @error('statement_college') is-invalid @enderror" name="statement_college" rows="8" maxlength="3000" data-count="200">{{ old('statement_college', $app->statement_college) }}</textarea>
    </div>
    @foreach ($chosen as $c)
        <div class="form-section">
            <h5>Why do you want to join {{ $c->course->name }}? <span class="req">*</span></h5>
            <textarea class="form-control @error('course_statement.'.$c->id) is-invalid @enderror" name="course_statement[{{ $c->id }}]" rows="6" maxlength="2000" data-count="100">{{ old('course_statement.'.$c->id, $c->statement) }}</textarea>
        </div>
    @endforeach
    @include('application._buttons')
</form>
@endsection
