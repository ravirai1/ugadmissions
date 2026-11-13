@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
<div class="row">
    @foreach ([
        ['Registrations', $registrations, 'fa-users', 'primary'],
        ['Email Verified', $verified, 'fa-user-check', 'success'],
        ['Registered Today', $todayRegistrations, 'fa-user-plus', 'info'],
    ] as [$label, $value, $icon, $color])
        <div class="col-sm-6 col-md-4">
            <div class="card card-stats card-round">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-icon"><div class="icon-big text-center icon-{{ $color }} bubble-shadow-small"><i class="fas {{ $icon }}"></i></div></div>
                        <div class="col col-stats ms-3 ms-sm-0"><div class="numbers"><p class="card-category">{{ $label }}</p><h4 class="card-title">{{ number_format($value) }}</h4></div></div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row">
    @foreach ([
        ['Applications Submitted', number_format($submitted), 'fa-file-alt', 'primary'],
        ['Fee Paid', number_format($feePaid), 'fa-check-double', 'success'],
        ['Unpaid (submitted)', number_format(max(0, $submitted - $feePaid)), 'fa-exclamation-circle', 'warning'],
    ] as [$label, $value, $icon, $color])
        <div class="col-sm-6 col-md-4">
            <div class="card card-stats card-round">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-icon"><div class="icon-big text-center icon-{{ $color }} bubble-shadow-small"><i class="fas {{ $icon }}"></i></div></div>
                        <div class="col col-stats ms-3 ms-sm-0"><div class="numbers"><p class="card-category">{{ $label }}</p><h4 class="card-title">{{ $value }}</h4></div></div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@if (auth('admin')->user()->hasRole(\App\Support\Roles::ADMIN, \App\Support\Roles::ACCOUNTS))
<div class="card card-round">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div class="card-title mb-2">Payments</div>
            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('admin.payments.check-pending') }}">@csrf<button class="btn btn-warning btn-sm" type="submit"><i class="fas fa-sync me-1"></i>Check payment status with SBI</button></form>
                <a href="{{ route('admin.payments.index') }}" class="btn btn-primary btn-sm">All payments</a>
            </div>
        </div>
        <div class="row text-center mt-2">
            <div class="col-6 col-md-3"><div class="small text-muted">Total collected</div><div class="fs-5 fw-bold">₹{{ number_format($collected, 2) }}</div></div>
            <div class="col-6 col-md-3"><div class="small text-muted">Collected today</div><div class="fs-5 fw-bold">₹{{ number_format($collectedToday, 2) }}</div></div>
            <div class="col-6 col-md-3"><a href="{{ route('admin.payments.index', ['status' => 'unconfirmed']) }}" class="text-decoration-none"><div class="small text-muted">Unconfirmed &gt; {{ config('payment.status_check_after_minutes') }} min</div><div class="fs-5 fw-bold text-warning">{{ number_format($unconfirmed) }}</div></a></div>
            <div class="col-6 col-md-3"><a href="{{ route('admin.payments.index', ['duplicates' => 1]) }}" class="text-decoration-none"><div class="small text-muted">Duplicates (refund)</div><div class="fs-5 fw-bold">{{ number_format($duplicates) }}</div></a></div>
        </div>
    </div>
</div>
@endif

@if (auth('admin')->user()->isSuperAdmin())
<div class="row">
    <div class="col-md-6">
        <div class="card card-round">
            <div class="card-body">
                <div class="card-title mb-2">Security today</div>
                <p class="mb-1">Failed logins: <strong>{{ $failedLoginsToday }}</strong></p>
                <p class="mb-0">Blocked IPs right now: <strong>{{ $blockedIps }}</strong> · <a href="{{ route('admin.security.blocked') }}">Manage</a></p>
            </div>
        </div>
    </div>
</div>
@endif

<div class="card card-round">
    <div class="card-body text-muted small">
        Course-wise, category-wise, gender, state, residence and PwBD statistics are added with the admin portal (step 4).
    </div>
</div>
@endsection
