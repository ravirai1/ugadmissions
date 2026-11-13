@extends('layouts.frontend')
@section('title', 'Reset Password')
@section('page_title', 'Reset Password')
@section('content')
<div class="container auth-card">
    <div class="card p-4">
        <form method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $email) }}" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">New password</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required autocomplete="new-password">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">At least 8 characters with upper and lower case letters, a number and a symbol.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirmation">Confirm new password</label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Reset Password</button>
        </form>
    </div>
</div>
@endsection
