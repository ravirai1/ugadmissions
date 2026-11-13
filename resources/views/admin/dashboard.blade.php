@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
@php
    $tile = function ($label, $value, $icon, $color, $href = null) {
        return compact('label', 'value', 'icon', 'color', 'href');
    };
    $bars = function (array $rows, string $labelKey, string $countKey = 'count') {
        $max = max(1, ...array_map(fn ($r) => $r[$countKey], $rows ?: [[$countKey => 1]]));
        return array_map(fn ($r) => $r + ['pct' => round($r[$countKey] / $max * 100)], $rows);
    };
@endphp

@isset($totals)
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="small text-muted">Figures for:</span>
        @foreach ($scopes as $key => $label)
            <a href="{{ route('admin.dashboard', ['scope' => $key]) }}" class="btn btn-sm {{ $scope === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
        <a href="{{ route('admin.dashboard.export', ['scope' => $scope]) }}" class="btn btn-success btn-sm ms-auto"><i class="fas fa-file-excel me-1"></i>Download statistics (Excel)</a>
    </div>

    <div class="row">
        @foreach ([
            $tile('Registrations', $totals['registrations'], 'fa-users', 'primary'),
            $tile('Forms in progress', $totals['started'], 'fa-edit', 'info'),
            $tile('Submitted', $totals['submitted'], 'fa-file-alt', 'secondary', route('admin.applications.index')),
            $tile('Fee paid', $totals['paid'], 'fa-check-double', 'success', route('admin.applications.index', ['fee' => 'paid'])),
            $tile('Residence', $summary['residence'], 'fa-home', 'warning', route('admin.applications.index', ['residence' => 1, 'fee' => $scope === 'paid' ? 'paid' : null])),
            $tile('PwBD', $summary['pwbd'], 'fa-wheelchair', 'info', route('admin.applications.index', ['pwbd' => 1, 'fee' => $scope === 'paid' ? 'paid' : null])),
        ] as $t)
            <div class="col-sm-6 col-md-4 col-xl-2">
                <a href="{{ $t['href'] ?? '#' }}" class="text-decoration-none">
                    <div class="card card-stats card-round">
                        <div class="card-body py-3">
                            <p class="card-category mb-1"><i class="fas {{ $t['icon'] }} text-{{ $t['color'] }} me-1"></i>{{ $t['label'] }}</p>
                            <h4 class="card-title mb-0">{{ number_format($t['value']) }}</h4>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-7">
            <div class="card card-round">
                <div class="card-body">
                    <div class="card-title mb-2">Course-wise applications <span class="small text-muted">({{ $scopes[$scope] }})</span></div>
                    <div class="table-responsive">
                        <table class="table table-sm stat-table mb-0">
                            <thead><tr><th>Course</th><th class="text-end">Total</th><th class="text-end">Female</th><th class="text-end">Christian</th><th class="text-end">JMC pref.</th><th class="text-end">Residence</th><th class="text-end">Added later</th></tr></thead>
                            <tbody>
                            @foreach ($courseWise as $r)
                                <tr>
                                    <td>{{ $r['course'] }}</td>
                                    <td class="text-end fw-bold">{{ $r['total'] }}</td>
                                    <td class="text-end">{{ $r['female'] }}</td>
                                    <td class="text-end">{{ $r['christian'] }}</td>
                                    <td class="text-end">{{ $r['jmc'] }}</td>
                                    <td class="text-end">{{ $r['residence'] }}</td>
                                    <td class="text-end">{{ $r['added_later'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mt-2 mb-0">One applicant can apply for several courses, so course totals add up to more than the number of applicants.</p>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card card-round">
                <div class="card-body">
                    <div class="card-title mb-2">Category-wise</div>
                    <table class="table table-sm stat-table mb-0">
                        @forelse ($bars($categoryWise, 'label') as $r)
                            <tr class="{{ $r['sub'] ? 'text-muted' : '' }}">
                                <td style="width:45%">{{ $r['label'] }}</td>
                                <td><div class="stat-bar" style="width: {{ $r['pct'] }}%"></div></td>
                                <td class="text-end" style="width:60px">{{ $r['count'] }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No applications yet.</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
            <div class="card card-round">
                <div class="card-body">
                    <div class="card-title mb-2">Gender</div>
                    <table class="table table-sm stat-table mb-0">
                        @forelse ($genderWise as $g => $n)
                            <tr><td>{{ $g }}</td><td class="text-end">{{ $n }}</td></tr>
                        @empty
                            <tr><td class="text-muted">No applications yet.</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @foreach (['cur' => 'Current address state', 'perm' => 'Permanent address state', 'domicile' => 'State belongs to'] as $by => $title)
            <div class="col-xl-4">
                <div class="card card-round">
                    <div class="card-body">
                        <div class="card-title mb-2">{{ $title }}</div>
                        <div style="max-height: 320px; overflow-y: auto">
                            <table class="table table-sm stat-table mb-0">
                                @forelse ($bars($stateWise[$by], 'state') as $r)
                                    <tr><td style="width:50%">{{ $r['state'] }}</td><td><div class="stat-bar" style="width: {{ $r['pct'] }}%"></div></td><td class="text-end" style="width:50px">{{ $r['count'] }}</td></tr>
                                @empty
                                    <tr><td class="text-muted">No applications yet.</td></tr>
                                @endforelse
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-4">
            <div class="card card-round">
                <div class="card-body">
                    <div class="card-title mb-2">PwBD by disability</div>
                    <table class="table table-sm stat-table mb-0">
                        @forelse ($pwbdWise as $r)
                            <tr><td>{{ $r['type'] }}<br><span class="small text-muted">{{ $r['group'] }}</span></td><td class="text-end">{{ $r['count'] }}</td></tr>
                        @empty
                            <tr><td class="text-muted">No PwBD applicants yet.</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card card-round">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="card-title mb-0">Payments</div>
                        <a href="{{ route('admin.payments.index') }}" class="small">All payments</a>
                    </div>
                    <table class="table table-sm stat-table">
                        <thead><tr><th>Purpose</th><th class="text-end">Paid</th><th class="text-end">Amount</th></tr></thead>
                        @forelse ($payments['byPurpose'] as $r)
                            <tr><td>{{ $r['purpose'] }}</td><td class="text-end">{{ $r['count'] }}</td><td class="text-end">₹{{ number_format($r['amount']) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No payments yet.</td></tr>
                        @endforelse
                        <tr class="fw-bold"><td>Total collected</td><td></td><td class="text-end">₹{{ number_format($totals['collected']) }}</td></tr>
                    </table>
                    <p class="small mb-2">Today: <strong>₹{{ number_format($payments['today']) }}</strong> ·
                        <a href="{{ route('admin.payments.index', ['status' => 'unconfirmed']) }}">Unconfirmed &gt; {{ config('payment.status_check_after_minutes') }} min: <strong>{{ $payments['unconfirmed'] }}</strong></a> ·
                        <a href="{{ route('admin.payments.index', ['duplicates' => 1]) }}">Duplicates: <strong>{{ $payments['duplicates'] }}</strong></a></p>
                    <form method="POST" action="{{ route('admin.payments.check-pending') }}">@csrf<button class="btn btn-warning btn-sm" type="submit"><i class="fas fa-sync me-1"></i>Check payment status with SBI</button></form>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card card-round">
                <div class="card-body">
                    <div class="card-title mb-2">Last 14 days</div>
                    <table class="table table-sm stat-table mb-0">
                        <thead><tr><th>Date</th><th class="text-end">Submitted</th><th class="text-end">Fee paid</th></tr></thead>
                        @foreach (array_reverse($daily) as $d)
                            <tr><td>{{ \Illuminate\Support\Carbon::parse($d['date'])->format('d M') }}</td><td class="text-end">{{ $d['submitted'] }}</td><td class="text-end">{{ $d['paid'] }}</td></tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="card card-round">
        <div class="card-body">
            <h5 class="mb-2">Welcome, {{ $admin->name }}</h5>
            <p class="mb-2 text-muted">{{ \App\Support\Roles::labels()[$admin->role] ?? $admin->role }}@if ($admin->department) · {{ $admin->department->name }}@endif</p>
            @isset($myCount)
                <p class="mb-3">Applications in your list: <strong>{{ number_format($myCount) }}</strong></p>
                <a href="{{ route('admin.applications.index') }}" class="btn btn-primary btn-sm">Open applications</a>
            @endisset
            @if (! empty($myCourses))
                <table class="table table-sm stat-table mt-3 mb-0" style="max-width: 520px">
                    <thead><tr><th>Course (fee paid)</th><th class="text-end">Applications</th><th class="text-end">Female</th></tr></thead>
                    @foreach ($myCourses as $r)<tr><td>{{ $r['course'] }}</td><td class="text-end">{{ $r['total'] }}</td><td class="text-end">{{ $r['female'] }}</td></tr>@endforeach
                </table>
            @endif
        </div>
    </div>
@endisset

@isset($security)
    <div class="card card-round">
        <div class="card-body">
            <div class="card-title mb-2">Security today</div>
            <p class="mb-0">Registrations today: <strong>{{ $security['registeredToday'] }}</strong> · Failed logins: <strong>{{ $security['failedLoginsToday'] }}</strong> · Blocked IPs right now: <strong>{{ $security['blockedIps'] }}</strong> · <a href="{{ route('admin.security.blocked') }}">Manage</a></p>
        </div>
    </div>
@endisset
@endsection
