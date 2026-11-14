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
            <div class="col-md-4">
                <label class="form-label" for="blood_group">Blood group <span class="req">*</span></label>
                <select class="form-select @error('blood_group') is-invalid @enderror" id="blood_group" name="blood_group" required>
                    <option value="">Select</option>
                    @foreach ($pd::BLOOD_GROUPS as $o)<option value="{{ $o }}" @selected(old('blood_group', $app->blood_group) === $o)>{{ $o }}</option>@endforeach
                </select>
                @error('blood_group')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="nationality">Nationality <span class="req">*</span></label>
                <select class="form-select @error('nationality') is-invalid @enderror" id="nationality" name="nationality" required>
                    <option value="">Select</option>
                    @foreach ($pd::NATIONALITIES as $o)<option value="{{ $o }}" @selected(old('nationality', $app->nationality) === $o)>{{ $o }}</option>@endforeach
                </select>
                @error('nationality')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="mother_tongue">Mother tongue <span class="req">*</span></label>
                <select class="form-select @error('mother_tongue') is-invalid @enderror" id="mother_tongue" name="mother_tongue" required>
                    <option value="">Select</option>
                    @foreach ($pd::MOTHER_TONGUES as $o)<option value="{{ $o }}" @selected(old('mother_tongue', $app->mother_tongue) === $o)>{{ $o }}</option>@endforeach
                </select>
                @error('mother_tongue')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 offset-md-8" data-show-when="mother_tongue=Other">
                <label class="form-label" for="mother_tongue_other">Your mother tongue <span class="req">*</span></label>
                <input class="form-control @error('mother_tongue_other') is-invalid @enderror" id="mother_tongue_other" name="mother_tongue_other" maxlength="60" value="{{ old('mother_tongue_other', $app->mother_tongue_other) }}" required>
                @error('mother_tongue_other')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    <div class="form-section">
        <h5>Family</h5>
        <p class="small text-muted">Choose "Late" as the title if a parent has passed away. Give a mobile number for at least one parent.</p>
@php
    $alive = implode('|', array_diff($pd::TITLES, [$pd::DECEASED]));
    $working = implode('|', $pd::WORKING);
@endphp
        <h6 class="mt-2 mb-3">Father</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <label class="form-label" for="father_title">Title <span class="req">*</span></label>
                <select class="form-select @error('father_title') is-invalid @enderror" id="father_title" name="father_title" required>
                    <option value="">Select</option>
                    @foreach ($pd::TITLES as $o)<option value="{{ $o }}" @selected(old('father_title', $app->father_title) === $o)>{{ $o }}</option>@endforeach
                </select>
                @error('father_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="father_name">Father's name <span class="req">*</span></label>
                <input class="form-control @error('father_name') is-invalid @enderror" id="father_name" name="father_name" maxlength="100" value="{{ old('father_name', $app->father_name) }}" required>
                @error('father_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="father_qualification">Qualification <span class="req">*</span></label>
                <select class="form-select @error('father_qualification') is-invalid @enderror" id="father_qualification" name="father_qualification" required>
                    <option value="">Select</option>
                    @foreach ($pd::QUALIFICATIONS as $o)<option value="{{ $o }}" @selected(old('father_qualification', $app->father_qualification) === $o)>{{ $o }}</option>@endforeach
                </select>
                @error('father_qualification')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 row g-3 m-0 p-0" data-show-when="father_title={{ $alive }}">
            <div class="col-md-4">
                <label class="form-label" for="father_occupation">Occupation <span class="req">*</span></label>
                <select class="form-select @error('father_occupation') is-invalid @enderror" id="father_occupation" name="father_occupation" required>
                    <option value="">Select</option>
                    @foreach ($pd::occupations() as $o)<option value="{{ $o }}" @selected(old('father_occupation', $app->father_occupation) === $o)>{{ $o }}</option>@endforeach
                </select>
                @error('father_occupation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="father_mobile">Mobile</label>
                <input class="form-control @error('father_mobile') is-invalid @enderror" id="father_mobile" name="father_mobile" maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}" value="{{ old('father_mobile', $app->father_mobile) }}" >
                @error('father_mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="father_email">Email</label>
                <input class="form-control @error('father_email') is-invalid @enderror" id="father_email" name="father_email" type="email" maxlength="150" value="{{ old('father_email', $app->father_email) }}" >
                @error('father_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
                <div class="col-12 row g-3 m-0 p-0" data-show-when="father_occupation={{ $working }}">
            <div class="col-md-4">
                <label class="form-label" for="father_designation">Designation <span class="req">*</span></label>
                <input class="form-control @error('father_designation') is-invalid @enderror" id="father_designation" name="father_designation" maxlength="100" value="{{ old('father_designation', $app->father_designation) }}" required>
                @error('father_designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-8">
                <label class="form-label" for="father_organisation">Organisation / employer <span class="req">*</span></label>
                <input class="form-control @error('father_organisation') is-invalid @enderror" id="father_organisation" name="father_organisation" maxlength="150" value="{{ old('father_organisation', $app->father_organisation) }}" required>
                @error('father_organisation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="father_office_address">Office address <span class="req">*</span></label>
                <textarea class="form-control @error('father_office_address') is-invalid @enderror" id="father_office_address" name="father_office_address" rows="2" maxlength="300" required>{{ old('father_office_address', $app->father_office_address) }}</textarea>
                @error('father_office_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
                </div>
            </div>
        </div>
        <h6 class="mt-2 mb-3">Mother</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <label class="form-label" for="mother_title">Title <span class="req">*</span></label>
                <select class="form-select @error('mother_title') is-invalid @enderror" id="mother_title" name="mother_title" required>
                    <option value="">Select</option>
                    @foreach ($pd::TITLES as $o)<option value="{{ $o }}" @selected(old('mother_title', $app->mother_title) === $o)>{{ $o }}</option>@endforeach
                </select>
                @error('mother_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="mother_name">Mother's name <span class="req">*</span></label>
                <input class="form-control @error('mother_name') is-invalid @enderror" id="mother_name" name="mother_name" maxlength="100" value="{{ old('mother_name', $app->mother_name) }}" required>
                @error('mother_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="mother_qualification">Qualification <span class="req">*</span></label>
                <select class="form-select @error('mother_qualification') is-invalid @enderror" id="mother_qualification" name="mother_qualification" required>
                    <option value="">Select</option>
                    @foreach ($pd::QUALIFICATIONS as $o)<option value="{{ $o }}" @selected(old('mother_qualification', $app->mother_qualification) === $o)>{{ $o }}</option>@endforeach
                </select>
                @error('mother_qualification')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 row g-3 m-0 p-0" data-show-when="mother_title={{ $alive }}">
            <div class="col-md-4">
                <label class="form-label" for="mother_occupation">Occupation <span class="req">*</span></label>
                <select class="form-select @error('mother_occupation') is-invalid @enderror" id="mother_occupation" name="mother_occupation" required>
                    <option value="">Select</option>
                    @foreach ($pd::occupations() as $o)<option value="{{ $o }}" @selected(old('mother_occupation', $app->mother_occupation) === $o)>{{ $o }}</option>@endforeach
                </select>
                @error('mother_occupation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="mother_mobile">Mobile</label>
                <input class="form-control @error('mother_mobile') is-invalid @enderror" id="mother_mobile" name="mother_mobile" maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}" value="{{ old('mother_mobile', $app->mother_mobile) }}" >
                @error('mother_mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="mother_email">Email</label>
                <input class="form-control @error('mother_email') is-invalid @enderror" id="mother_email" name="mother_email" type="email" maxlength="150" value="{{ old('mother_email', $app->mother_email) }}" >
                @error('mother_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
                <div class="col-12 row g-3 m-0 p-0" data-show-when="mother_occupation={{ $working }}">
            <div class="col-md-4">
                <label class="form-label" for="mother_designation">Designation <span class="req">*</span></label>
                <input class="form-control @error('mother_designation') is-invalid @enderror" id="mother_designation" name="mother_designation" maxlength="100" value="{{ old('mother_designation', $app->mother_designation) }}" required>
                @error('mother_designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-8">
                <label class="form-label" for="mother_organisation">Organisation / employer <span class="req">*</span></label>
                <input class="form-control @error('mother_organisation') is-invalid @enderror" id="mother_organisation" name="mother_organisation" maxlength="150" value="{{ old('mother_organisation', $app->mother_organisation) }}" required>
                @error('mother_organisation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="mother_office_address">Office address <span class="req">*</span></label>
                <textarea class="form-control @error('mother_office_address') is-invalid @enderror" id="mother_office_address" name="mother_office_address" rows="2" maxlength="300" required>{{ old('mother_office_address', $app->mother_office_address) }}</textarea>
                @error('mother_office_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
                </div>
            </div>
        </div>
        <div class="row g-3">
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
