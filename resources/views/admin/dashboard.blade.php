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
        Course-wise, category-wise, gender, state, residence, PwBD and payment statistics appear here once the application form and payments are live.
    </div>
</div>
@endsection
