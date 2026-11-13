@extends('layouts.frontend')
@section('title', 'Forgot Password')
@section('page_title', 'Forgot Password')
@section('content')
<div class="container auth-card">
    <div class="card p-4">
        <p class="text-muted">Enter your registered email and we will send you a link to reset your password.</p>
        <form method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @include('partials.captcha')
            <button type="submit" class="btn btn-primary w-100 py-2">Send Reset Link</button>
        </form>
        <p class="text-center mt-3 mb-0"><a href="{{ route('login') }}">Back to login</a></p>
    </div>
</div>
@endsection
