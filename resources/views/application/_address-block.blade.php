<div class="row g-3">
    <div class="col-md-6"><label class="form-label">House / street <span class="req">*</span></label><input class="form-control @error($p.'_line1') is-invalid @enderror" name="{{ $p }}_line1" maxlength="200" value="{{ old($p.'_line1', $app->{$p.'_line1'}) }}"></div>
    <div class="col-md-6"><label class="form-label">Locality / area</label><input class="form-control" name="{{ $p }}_line2" maxlength="200" value="{{ old($p.'_line2', $app->{$p.'_line2'}) }}"></div>
    <div class="col-md-4"><label class="form-label">City / town <span class="req">*</span></label><input class="form-control @error($p.'_city') is-invalid @enderror" name="{{ $p }}_city" maxlength="100" value="{{ old($p.'_city', $app->{$p.'_city'}) }}"></div>
    <div class="col-md-4">
        <label class="form-label">State <span class="req">*</span></label>
        <select class="form-select @error($p.'_state_id') is-invalid @enderror" name="{{ $p }}_state_id" data-state-select="{{ $p }}">
            <option value="">Select state</option>
            @foreach ($states as $s)<option value="{{ $s->id }}" @selected((string) old($p.'_state_id', $app->{$p.'_state_id'}) === (string) $s->id)>{{ $s->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-4">
        <div class="district-select">
            <label class="form-label">District <span class="req">*</span></label>
            <select class="form-select @error($p.'_district_id') is-invalid @enderror" name="{{ $p }}_district_id" data-current="{{ old($p.'_district_id', $app->{$p.'_district_id'}) }}"></select>
        </div>
        <div data-district-other="{{ $p }}" class="d-none">
            <label class="form-label">District <span class="req">*</span></label>
            <input class="form-control @error($p.'_district_other') is-invalid @enderror" name="{{ $p }}_district_other" maxlength="100" value="{{ old($p.'_district_other', $app->{$p.'_district_other'}) }}">
        </div>
    </div>
    <div class="col-md-4"><label class="form-label">PIN code <span class="req">*</span></label><input class="form-control @error($p.'_pin') is-invalid @enderror" name="{{ $p }}_pin" maxlength="6" inputmode="numeric" data-digits-only value="{{ old($p.'_pin', $app->{$p.'_pin'}) }}"></div>
</div>
