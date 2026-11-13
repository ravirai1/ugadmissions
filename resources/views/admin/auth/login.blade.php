@extends('layouts.admin-auth')
@section('content')
<form method="POST" action="{{ route('admin.login') }}" novalidate>
    @csrf
    <div class="form-group px-0">
        <label for="email">Email</label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="form-group px-0">
        <label for="password">Password</label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
    </div>
    <div class="form-group px-0">
        <label for="captcha">Security check: <span class="captcha-q">{{ $captcha }}</span> = ?</label>
        <input type="text" inputmode="numeric" maxlength="3" autocomplete="off" class="form-control @error('captcha') is-invalid @enderror" id="captcha" name="captcha" required data-digits-only>
        @error('captcha')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-primary w-100 mt-2">Continue</button>
    <p class="small text-muted text-center mt-3 mb-0">Authorised staff only. All access is logged. Repeated failed attempts block your IP address.</p>
</form>
@endsection
