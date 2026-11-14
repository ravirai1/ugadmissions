@extends('layouts.frontend')
@section('title', 'Register')
@section('page_title', 'Applicant Registration')
@section('content')
<div class="container auth-card">
    <div class="card p-4">
        <p class="text-muted small mb-4">Fields marked <span class="text-primary">*</span> are required. Use the same CUET 2027 application number as on your NTA confirmation page. Type each detail twice; copy and paste are turned off so that typing mistakes are caught.</p>
        <form method="POST" action="{{ route('register') }}" novalidate data-no-paste>
            @csrf
            <div class="mb-3">
                <label class="form-label" for="name">Full name (as in Class 10 certificate) <span class="req">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" maxlength="100" required autocomplete="name">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="name_confirmation">Re-enter full name <span class="req">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name_confirmation" name="name_confirmation" value="{{ old('name_confirmation') }}" required autocomplete="off" data-match="name" maxlength="100">
                <div class="invalid-feedback" data-match-msg>Does not match.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email <span class="req">*</span></label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" maxlength="150" required autocomplete="email">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="email_confirmation">Re-enter email <span class="req">*</span></label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email_confirmation" name="email_confirmation" value="{{ old('email_confirmation') }}" required autocomplete="off" data-match="email" maxlength="150">
                <div class="invalid-feedback" data-match-msg>Does not match.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="mobile">Mobile number <span class="req">*</span></label>
                <div class="input-group has-validation">
                    <span class="input-group-text">+91</span>
                    <input type="text" inputmode="numeric" class="form-control @error('mobile') is-invalid @enderror" id="mobile" name="mobile" value="{{ old('mobile') }}" maxlength="10" required data-digits-only autocomplete="tel-national">
                    @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="mobile_confirmation">Re-enter mobile number <span class="req">*</span></label>
                <input type="text" class="form-control @error('mobile') is-invalid @enderror" id="mobile_confirmation" name="mobile_confirmation" value="{{ old('mobile_confirmation') }}" required autocomplete="off" data-match="mobile" inputmode="numeric" maxlength="10" data-digits-only>
                <div class="invalid-feedback" data-match-msg>Does not match.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="cuet_no">CUET (UG) 2027 application number <span class="req">*</span></label>
                <input type="text" inputmode="numeric" class="form-control @error('cuet_no') is-invalid @enderror" id="cuet_no" name="cuet_no" value="{{ old('cuet_no') }}" maxlength="15" placeholder="27XXXXXXXXXX" required data-digits-only>
                <div class="form-text">Must start with {{ config('admissions.cuet_prefix') }}.</div>
                @error('cuet_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="cuet_no_confirmation">Re-enter CUET application number <span class="req">*</span></label>
                <input type="text" class="form-control @error('cuet_no') is-invalid @enderror" id="cuet_no_confirmation" name="cuet_no_confirmation" value="{{ old('cuet_no_confirmation') }}" required autocomplete="off" data-match="cuet_no" inputmode="numeric" maxlength="15" data-digits-only>
                <div class="invalid-feedback" data-match-msg>Does not match.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password <span class="req">*</span></label>
                <div class="input-group has-validation">
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required autocomplete="new-password">
                    <button class="btn btn-outline-secondary" type="button" data-toggle-password="password">Show</button>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-text">At least 8 characters with upper and lower case letters, a number and a symbol.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirmation">Re-enter password <span class="req">*</span></label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" data-match="password">
                <div class="invalid-feedback" data-match-msg>Does not match.</div>
            </div>
            @include('partials.captcha')
            <button type="submit" class="btn btn-primary w-100 py-2">Create Account</button>
        </form>
        <p class="text-center mt-3 mb-0">Already registered? <a href="{{ route('login') }}">Log in</a></p>
    </div>
</div>
@endsection
