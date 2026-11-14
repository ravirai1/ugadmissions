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

    <div class="du-alert mb-4" role="alert" data-show-when="category_code={{ \App\Models\Category::CHRISTIAN }}">
        <h5 class="mb-2"><i class="fa fa-exclamation-triangle me-2"></i>Christian applicants: select Christian minority on the DU CSAS (UG) portal too</h5>
        <p class="mb-2"><strong>On the DU CSAS (UG) portal you MUST choose the Christian minority category for St. Stephen's College.</strong></p>
        <p class="mb-2">If you do not, <strong>you will not be considered under the Christian category</strong>, even if you choose it here. You need to apply in both places.</p>
        <a href="{{ config('admissions.du_csas_url') }}" target="_blank" rel="noopener" class="btn btn-sm btn-dark"><i class="fa fa-external-link-alt me-1"></i>Open the DU CSAS (UG) portal</a>
        <div class="form-check mt-3">
            <input class="form-check-input @error('du_minority_confirm') is-invalid @enderror" type="checkbox" name="du_minority_confirm" id="du_minority_confirm" value="1" @checked(old('du_minority_confirm', $app->category_code === \App\Models\Category::CHRISTIAN && $app->christian_subcategory))>
            <label class="form-check-label fw-bold" for="du_minority_confirm">I have selected / will select the Christian minority category for St. Stephen's College on the DU CSAS (UG) portal. <span class="req">*</span></label>
            @error('du_minority_confirm')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
            <div class="col-12"><hr class="my-1"><strong>Baptism details</strong>
                <div class="form-text">Baptism must be at least {{ $baptismYears }} years before the date of application, that is on or before {{ now()->subYears($baptismYears)->format('d M Y') }}.</div>
            </div>
            <div class="col-12">
                <label class="form-label d-block">Are you baptised? <span class="req">*</span></label>
                @php($bap = old('baptised', $b['baptised'] ?? 'yes'))
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="baptised" id="baptised_yes" value="yes" @checked($bap === 'yes')><label class="form-check-label" for="baptised_yes">Yes</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="baptised" id="baptised_no" value="no" @checked($bap === 'no')><label class="form-check-label" for="baptised_no">No, I will give my parent's baptism details</label></div>
            </div>
            <div class="col-12" data-show-when="baptised=yes">
              <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="baptism_date">Date of baptism <span class="req">*</span></label><input type="date" class="form-control @error('baptism_date') is-invalid @enderror" id="baptism_date" name="baptism_date" max="{{ now()->subYears($baptismYears)->toDateString() }}" value="{{ old('baptism_date', $b['baptism_date'] ?? '') }}">@error('baptism_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-8"><label class="form-label" for="baptism_church">Church where baptised <span class="req">*</span></label><input class="form-control @error('baptism_church') is-invalid @enderror" id="baptism_church" name="baptism_church" maxlength="200" value="{{ old('baptism_church', $b['baptism_church'] ?? '') }}">@error('baptism_church')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="baptism_place">Place of baptism <span class="req">*</span></label><input class="form-control @error('baptism_place') is-invalid @enderror" id="baptism_place" name="baptism_place" maxlength="100" value="{{ old('baptism_place', $b['baptism_place'] ?? '') }}">@error('baptism_place')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="baptism_priest">Name of priest / minister who baptised <span class="req">*</span></label><input class="form-control @error('baptism_priest') is-invalid @enderror" id="baptism_priest" name="baptism_priest" maxlength="100" value="{{ old('baptism_priest', $b['baptism_priest'] ?? '') }}">@error('baptism_priest')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="baptism_priest_designation">Priest's designation</label><input class="form-control @error('baptism_priest_designation') is-invalid @enderror" id="baptism_priest_designation" name="baptism_priest_designation" maxlength="100" placeholder="e.g. Rev., Fr., Pastor" value="{{ old('baptism_priest_designation', $b['baptism_priest_designation'] ?? '') }}">@error('baptism_priest_designation')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
              </div>
            </div>
            <div class="col-12" data-show-when="baptised=no">
              <p class="small text-muted mb-2">Give the baptism details of your father, your mother, or both, and upload the parent's baptism certificate on the Uploads step.</p>
              <div class="row g-3 mb-3">
                <div class="col-12"><em>Father's baptism</em></div>
                <div class="col-md-3"><label class="form-label" for="father_baptism_date">Date of baptism</label><input type="date" class="form-control @error('father_baptism_date') is-invalid @enderror" id="father_baptism_date" name="father_baptism_date" max="{{ now()->subYears($baptismYears)->toDateString() }}" value="{{ old('father_baptism_date', $b['father_baptism_date'] ?? '') }}">@error('father_baptism_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="father_baptism_church">Church where baptised</label><input class="form-control @error('father_baptism_church') is-invalid @enderror" id="father_baptism_church" name="father_baptism_church" maxlength="200" value="{{ old('father_baptism_church', $b['father_baptism_church'] ?? '') }}">@error('father_baptism_church')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-2"><label class="form-label" for="father_baptism_place">Place</label><input class="form-control @error('father_baptism_place') is-invalid @enderror" id="father_baptism_place" name="father_baptism_place" maxlength="100" value="{{ old('father_baptism_place', $b['father_baptism_place'] ?? '') }}">@error('father_baptism_place')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label" for="father_baptism_priest">Baptised by</label><input class="form-control @error('father_baptism_priest') is-invalid @enderror" id="father_baptism_priest" name="father_baptism_priest" maxlength="100" value="{{ old('father_baptism_priest', $b['father_baptism_priest'] ?? '') }}">@error('father_baptism_priest')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-12"><em>Mother's baptism</em></div>
                <div class="col-md-3"><label class="form-label" for="mother_baptism_date">Date of baptism</label><input type="date" class="form-control @error('mother_baptism_date') is-invalid @enderror" id="mother_baptism_date" name="mother_baptism_date" max="{{ now()->subYears($baptismYears)->toDateString() }}" value="{{ old('mother_baptism_date', $b['mother_baptism_date'] ?? '') }}">@error('mother_baptism_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="mother_baptism_church">Church where baptised</label><input class="form-control @error('mother_baptism_church') is-invalid @enderror" id="mother_baptism_church" name="mother_baptism_church" maxlength="200" value="{{ old('mother_baptism_church', $b['mother_baptism_church'] ?? '') }}">@error('mother_baptism_church')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-2"><label class="form-label" for="mother_baptism_place">Place</label><input class="form-control @error('mother_baptism_place') is-invalid @enderror" id="mother_baptism_place" name="mother_baptism_place" maxlength="100" value="{{ old('mother_baptism_place', $b['mother_baptism_place'] ?? '') }}">@error('mother_baptism_place')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label" for="mother_baptism_priest">Baptised by</label><input class="form-control @error('mother_baptism_priest') is-invalid @enderror" id="mother_baptism_priest" name="mother_baptism_priest" maxlength="100" value="{{ old('mother_baptism_priest', $b['mother_baptism_priest'] ?? '') }}">@error('mother_baptism_priest')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
              </div>
            </div>
            <div class="col-12"><hr class="my-1"><strong>Present church and parish priest</strong></div>
            <div class="col-md-4"><label class="form-label" for="denomination">Denomination <span class="req">*</span></label><input class="form-control @error('denomination') is-invalid @enderror" id="denomination" name="denomination" maxlength="100" value="{{ old('denomination', $b['denomination'] ?? '') }}">@error('denomination')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-8"><label class="form-label" for="current_church">Present church / parish <span class="req">*</span></label><input class="form-control @error('current_church') is-invalid @enderror" id="current_church" name="current_church" maxlength="200" value="{{ old('current_church', $b['current_church'] ?? '') }}">@error('current_church')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label" for="current_church_address">Address of the church / parish <span class="req">*</span></label><textarea class="form-control @error('current_church_address') is-invalid @enderror" id="current_church_address" name="current_church_address" rows="2" maxlength="300">{{ old('current_church_address', $b['current_church_address'] ?? '') }}</textarea>@error('current_church_address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label" for="current_priest">Parish priest / minister <span class="req">*</span></label><input class="form-control @error('current_priest') is-invalid @enderror" id="current_priest" name="current_priest" maxlength="100" value="{{ old('current_priest', $b['current_priest'] ?? '') }}">@error('current_priest')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label" for="current_priest_mobile">Parish priest's mobile <span class="req">*</span></label><input class="form-control @error('current_priest_mobile') is-invalid @enderror" id="current_priest_mobile" name="current_priest_mobile" maxlength="10" inputmode="numeric" value="{{ old('current_priest_mobile', $b['current_priest_mobile'] ?? '') }}">@error('current_priest_mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label" for="current_priest_email">Parish priest's email</label><input class="form-control @error('current_priest_email') is-invalid @enderror" id="current_priest_email" name="current_priest_email" maxlength="150" type="email" value="{{ old('current_priest_email', $b['current_priest_email'] ?? '') }}">@error('current_priest_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
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
