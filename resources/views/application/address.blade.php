@extends('application._layout')
@section('step')
<script type="application/json" id="districts-data" nonce="{{ $cspNonce }}">@json($districts)</script>
<form method="POST" action="{{ route('application.save', $step) }}" data-app-form novalidate>
    @csrf
    <div class="form-section">
        <h5>Current address</h5>
        @include('application._address-block', ['p' => 'cur'])
    </div>
    <div class="form-section">
        <h5>Permanent address</h5>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="perm_same" id="perm_same" value="1" @checked(old('perm_same', $app->perm_same))>
            <label class="form-check-label" for="perm_same">Same as current address</label>
        </div>
        <div data-show-when="perm_same" data-hide>
            @include('application._address-block', ['p' => 'perm'])
        </div>
    </div>
    <div class="form-section">
        <h5>State you belong to</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="domicile_state_id">Domicile / native state <span class="req">*</span></label>
                <select class="form-select @error('domicile_state_id') is-invalid @enderror" id="domicile_state_id" name="domicile_state_id">
                    <option value="">Select state</option>
                    @foreach ($states as $s)<option value="{{ $s->id }}" @selected((string) old('domicile_state_id', $app->domicile_state_id) === (string) $s->id)>{{ $s->name }}</option>@endforeach
                </select>
                <div class="form-text">The state you originally belong to, even if you live elsewhere now.</div>
            </div>
        </div>
    </div>
    @include('application._buttons')
</form>
@endsection
