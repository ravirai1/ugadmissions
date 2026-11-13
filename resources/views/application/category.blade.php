@extends('application._layout')
@section('step')
@php($b = $app->baptism_details ?? [])
<form method="POST" action="{{ route('application.save', $step) }}" data-app-form novalidate>
    @csrf
    <div class="form-section">
        <h5>Category</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="category_code">Category <span class="req">*</span></label>
                <select class="form-select @error('category_code') is-invalid @enderror" id="category_code" name="category_code" required>
                    <option value="">Select category</option>
                    @foreach ($categories as $c)<option value="{{ $c->code }}" @selected(old('category_code', $app->category_code) === $c->code)>{{ $c->name }}</option>@endforeach
                </select>
                <div class="form-text">Christian applicants: please read the <a href="{{ route('christian') }}" target="_blank">guidelines for Christian students</a>.</div>
            </div>
        </div>
    </div>

    <div class="form-section" data-show-when="category_code={{ \App\Models\Category::CHRISTIAN }}">
        <h5>Christian category details</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="christian_subcategory">Sub-category <span class="req">*</span></label>
                <select class="form-select @error('christian_subcategory') is-invalid @enderror" id="christian_subcategory" name="christian_subcategory">
                    <option value="">Select sub-category</option>
                    @foreach ($subcategories as $c)<option value="{{ $c->code }}" @selected(old('christian_subcategory', $app->christian_subcategory) === $c->code)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_cst" id="is_cst" value="1" @checked(old('is_cst', $app->is_cst))>
                    <label class="form-check-label" for="is_cst"><strong>CST</strong> – I am a Christian belonging to a Scheduled Tribe (ST certificate required)</label>
                </div>
            </div>
            <div class="col-12"><hr class="my-1"><strong>Baptism details</strong></div>
            <div class="col-md-4"><label class="form-label">Date of baptism <span class="req">*</span></label><input type="date" class="form-control @error('baptism_date') is-invalid @enderror" name="baptism_date" value="{{ old('baptism_date', $b['baptism_date'] ?? '') }}"></div>
            <div class="col-md-8"><label class="form-label">Church where baptised <span class="req">*</span></label><input class="form-control @error('baptism_church') is-invalid @enderror" name="baptism_church" maxlength="200" value="{{ old('baptism_church', $b['baptism_church'] ?? '') }}"></div>
            <div class="col-md-4"><label class="form-label">Place of baptism <span class="req">*</span></label><input class="form-control @error('baptism_place') is-invalid @enderror" name="baptism_place" maxlength="100" value="{{ old('baptism_place', $b['baptism_place'] ?? '') }}"></div>
            <div class="col-md-4"><label class="form-label">Name of priest / minister who baptised <span class="req">*</span></label><input class="form-control @error('baptism_priest') is-invalid @enderror" name="baptism_priest" maxlength="100" value="{{ old('baptism_priest', $b['baptism_priest'] ?? '') }}"></div>
            <div class="col-md-4"><label class="form-label">Priest's designation</label><input class="form-control" name="baptism_priest_designation" maxlength="100" placeholder="e.g. Rev., Fr., Pastor" value="{{ old('baptism_priest_designation', $b['baptism_priest_designation'] ?? '') }}"></div>
            <div class="col-md-4"><label class="form-label">Denomination <span class="req">*</span></label><input class="form-control @error('denomination') is-invalid @enderror" name="denomination" maxlength="100" value="{{ old('denomination', $b['denomination'] ?? '') }}"></div>
            <div class="col-md-4"><label class="form-label">Present church / parish <span class="req">*</span></label><input class="form-control @error('current_church') is-invalid @enderror" name="current_church" maxlength="200" value="{{ old('current_church', $b['current_church'] ?? '') }}"></div>
            <div class="col-md-4"><label class="form-label">Present priest / minister <span class="req">*</span></label><input class="form-control @error('current_priest') is-invalid @enderror" name="current_priest" maxlength="100" value="{{ old('current_priest', $b['current_priest'] ?? '') }}"></div>
        </div>
    </div>

    <div class="form-section">
        <h5>Person with Benchmark Disability (PwBD)</h5>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_pwbd" id="is_pwbd" value="1" @checked(old('is_pwbd', $app->is_pwbd))>
            <label class="form-check-label" for="is_pwbd">I am a person with benchmark disability (40% or more)</label>
        </div>
        <div class="row g-3" data-show-when="is_pwbd">
            <div class="col-md-6">
                <label class="form-label">Type of disability <span class="req">*</span></label>
                <select class="form-select @error('pwbd_type_id') is-invalid @enderror" name="pwbd_type_id">
                    <option value="">Select type</option>
                    @foreach ($pwbdTypes as $group => $types)
                        <optgroup label="{{ $types->first()->group_no }}. {{ $group }}">
                            @foreach ($types as $t)<option value="{{ $t->id }}" @selected((string) old('pwbd_type_id', $app->pwbd_type_id) === (string) $t->id)>{{ $t->name }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Disability % <span class="req">*</span></label><input type="number" min="40" max="100" class="form-control @error('pwbd_percentage') is-invalid @enderror" name="pwbd_percentage" value="{{ old('pwbd_percentage', $app->pwbd_percentage) }}"></div>
            <div class="col-md-3"><label class="form-label">UDID number <span class="req">*</span></label><input class="form-control @error('udid_no') is-invalid @enderror" name="udid_no" maxlength="30" value="{{ old('udid_no', $app->udid_no) }}"></div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="needs_scribe" id="needs_scribe" value="1" @checked(old('needs_scribe', $app->needs_scribe))>
                    <label class="form-check-label" for="needs_scribe">I will need a scribe or extra time for the interview</label>
                </div>
            </div>
        </div>
    </div>
    @include('application._buttons')
</form>
@endsection
