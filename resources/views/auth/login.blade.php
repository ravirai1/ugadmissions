@extends('layouts.frontend')
@section('title', 'Login')
@section('page_title', 'Applicant Login')
@section('content')
<div class="container auth-card">
    <div class="card p-4">
        <form method="POST" action="{{ route('login') }}" novalidate>
            @csrf
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" data-toggle-password="password">Show</button>
                </div>
            </div>
            @include('partials.captcha')
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <a href="{{ route('password.request') }}">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Log In</button>
        </form>
        <p class="text-center mt-3 mb-0">New applicant? <a href="{{ route('register') }}">Create an account</a></p>
    </div>
</div>
@endsection
