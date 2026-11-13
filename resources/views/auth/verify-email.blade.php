@extends('layouts.frontend')
@section('title', 'Verify Email')
@section('page_title', 'Verify Your Email')
@section('content')
<div class="container auth-card">
    <div class="card p-4 text-center">
        <i class="fa fa-envelope-open-text fa-3x text-primary mb-3"></i>
        <p>We have sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Click the link in that email to activate your account.</p>
        <p class="text-muted small">Didn't get it? Check your spam folder, or request a new link below.</p>
        <form method="POST" action="{{ route('verification.send') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">Resend Verification Email</button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="d-inline ms-2">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">Log Out</button>
        </form>
    </div>
</div>
@endsection
