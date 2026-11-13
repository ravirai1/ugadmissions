@extends('layouts.frontend')
@section('title', 'Dashboard')
@section('page_title', 'Applicant Dashboard')
@section('content')
<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Welcome, {{ $user->name }}</h4>
            <p class="text-muted mb-0 small">CUET No: <strong>{{ $user->cuet_no }}</strong> · Email: {{ $user->email }} · Mobile: +91 {{ $user->mobile }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="fa fa-sign-out-alt me-1"></i>Log Out</button>
        </form>
    </div>

    <div class="stage-tracker mb-5">
        @foreach ($stages as $i => $stage)
            <div class="stage {{ $i < $currentStage ? 'done' : ($i === $currentStage ? 'current' : '') }}">
                <div class="fw-bold">{{ $i + 1 }}</div>{{ $stage }}
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="p-4 border h-100">
                <h5 class="mb-3">Your Application</h5>
                <p class="text-muted">You have not started your application yet.</p>
                <a href="#" class="btn btn-primary py-2 px-4 disabled" aria-disabled="true"><i class="fa fa-edit me-2"></i>Apply Now</a>
                <p class="small text-muted mt-2 mb-0">The application form opens in the next release.</p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="p-4 bg-light h-100">
                <h5 class="mb-3">Notices</h5>
                <p class="small text-muted mb-0">No notices yet.</p>
            </div>
        </div>
    </div>
</div>
@endsection
