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
                @if (! $app)
                    <p class="text-muted">You have not started your application yet.</p>
                    <a href="{{ route('application.start') }}" class="btn btn-primary py-2 px-4"><i class="fa fa-edit me-2"></i>Apply Now</a>
                @elseif (! $app->isSubmitted())
                    <p class="mb-2">Application in progress: <strong>{{ $progress }}%</strong> complete.</p>
                    <div class="progress mb-3" style="height:8px"><div class="progress-bar bg-primary" style="width: {{ $progress }}%"></div></div>
                    <a href="{{ route('application.start') }}" class="btn btn-primary py-2 px-4"><i class="fa fa-edit me-2"></i>Continue Application</a>
                @else
                    <p class="mb-1">Application no.: <strong>{{ $app->application_no }}</strong></p>
                    <p class="mb-2 small text-muted">Submitted {{ $app->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</p>
                    <ul class="small">
                        @foreach ($app->courses as $c)<li>{{ $c->course->name }} (DU pref. {{ $c->du_preference }})</li>@endforeach
                        @if ($app->residence)<li>Residence</li>@endif
                    </ul>
                    @if ($app->isPaid())
                        <div class="alert alert-success py-2 small mb-3"><i class="fa fa-check-circle me-1"></i>Application fee paid on {{ $app->fee_paid_at->timezone('Asia/Kolkata')->format('d M Y') }}. Your application is with the College for scrutiny.</div>
                        <a href="{{ route('payment.index') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i>Add course / residence</a>
                    @else
                        <div class="alert alert-warning py-2 small mb-3">Fee payment pending. Your application is complete only after the fee is paid.</div>
                        <a href="{{ route('payment.index') }}" class="btn btn-primary btn-sm"><i class="fa fa-rupee-sign me-1"></i>Pay Application Fee</a>
                    @endif
                    <a href="{{ route('application.step', 'preview') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-eye me-1"></i>View Application</a>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="p-4 bg-light h-100">
                <h5 class="mb-3">Notices</h5>
                <p class="small text-muted mb-0">No notices yet.</p>
            </div>
        </div>
    </div>

    @if ($app?->isSubmitted())
        <div class="p-4 border mt-4">
            <h5 class="mb-3">Payments &amp; Receipts</h5>
            @include('payment._history', ['payments' => $payments])
        </div>
    @endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('frontend/js/ssc-pay.js') }}"></script>
@endpush
