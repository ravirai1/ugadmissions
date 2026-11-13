@extends('layouts.admin')
@section('title', 'Fees')
@section('content')
<div class="card" style="max-width: 640px">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.fees.update') }}">
            @csrf @method('PUT')
            @foreach ($labels as $key => $label)
                <div class="row mb-3 align-items-center">
                    <label class="col-sm-7 col-form-label" for="{{ $key }}">{{ $label }}</label>
                    <div class="col-sm-5">
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" min="0" max="100000" class="form-control @error($key) is-invalid @enderror" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $fees[$key]) }}" required>
                        </div>
                    </div>
                </div>
            @endforeach
            <p class="small text-muted">New fees apply to payments started after saving. Payments already started keep the amount shown to the applicant at that time. Every change is recorded in the audit log.</p>
            <button class="btn btn-primary" type="submit">Save fees</button>
        </form>
    </div>
</div>
@endsection
