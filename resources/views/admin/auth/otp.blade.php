@extends('layouts.admin-auth')
@section('title', 'Verify Code')
@section('heading', 'Enter Login Code')
@section('content')
<p class="text-muted small">We emailed a 6-digit code to <strong>{{ $email }}</strong>. It expires in {{ config('admissions.otp.expires_minutes') }} minutes.</p>
<form method="POST" action="{{ route('admin.otp.verify') }}" novalidate>
    @csrf
    <div class="form-group px-0">
        <label for="otp">Login code</label>
        <input type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="form-control form-control-lg text-center @error('otp') is-invalid @enderror" id="otp" name="otp" required autofocus data-digits-only>
        @error('otp')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-primary w-100">Verify &amp; Log In</button>
</form>
<div class="d-flex justify-content-between mt-3">
    <form method="POST" action="{{ route('admin.otp.resend') }}">@csrf<button class="btn btn-link btn-sm p-0" type="submit">Resend code</button></form>
    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="btn btn-link btn-sm p-0 text-muted" type="submit">Cancel</button></form>
</div>
@endsection
