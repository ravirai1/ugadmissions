@extends('application._layout')
@section('step')
<form method="POST" action="{{ route('application.save', $step) }}" data-app-form novalidate>
    @csrf
    <div class="form-section">
        <h5>Applicant</h5>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" value="{{ $user->name }}" disabled></div>
            <div class="col-md-6"><label class="form-label">CUET application no.</label><input class="form-control" value="{{ $user->cuet_no }}" disabled></div>
            <div class="col-md-4">
                <label class="form-label" for="dob">Date of birth <span class="req">*</span></label>
                <input type="date" class="form-control @error('dob') is-invalid @enderror" id="dob" name="dob" value="{{ old('dob', $app->dob?->format('Y-m-d')) }}" required>
            </div>
            <div class="col-md-8">
                <label class="form-label d-block">Gender <span class="req">*</span></label>
                @foreach ($genders as $g)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="gender" id="g{{ $loop->index }}" value="{{ $g }}" @checked(old('gender', $app->gender) === $g) required>
                        <label class="form-check-label" for="g{{ $loop->index }}">{{ $g }}</label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="form-section">
        <h5>Family</h5>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="father_name">Father's name <span class="req">*</span></label><input class="form-control @error('father_name') is-invalid @enderror" id="father_name" name="father_name" maxlength="100" value="{{ old('father_name', $app->father_name) }}" required></div>
            <div class="col-md-6"><label class="form-label" for="mother_name">Mother's name <span class="req">*</span></label><input class="form-control @error('mother_name') is-invalid @enderror" id="mother_name" name="mother_name" maxlength="100" value="{{ old('mother_name', $app->mother_name) }}" required></div>
            <div class="col-md-6">
                <label class="form-label" for="family_income">Annual family income <span class="req">*</span></label>
                <select class="form-select @error('family_income') is-invalid @enderror" id="family_income" name="family_income" required>
                    <option value="">Select</option>
                    @foreach ($incomes as $k => $v)<option value="{{ $k }}" @selected(old('family_income', $app->family_income) === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="form-section">
        <h5>Last school attended</h5>
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label" for="last_school">School name <span class="req">*</span></label><input class="form-control @error('last_school') is-invalid @enderror" id="last_school" name="last_school" maxlength="200" value="{{ old('last_school', $app->last_school) }}" required></div>
            <div class="col-md-4"><label class="form-label" for="last_school_city">City <span class="req">*</span></label><input class="form-control @error('last_school_city') is-invalid @enderror" id="last_school_city" name="last_school_city" maxlength="100" value="{{ old('last_school_city', $app->last_school_city) }}" required></div>
        </div>
    </div>
    @include('application._buttons')
</form>
@endsection
