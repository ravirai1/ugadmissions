@extends('layouts.admin')
@section('title', 'Payments')
@section('content')
@php $badge = ['success' => 'success', 'pending' => 'warning', 'initiated' => 'secondary', 'failed' => 'danger']; @endphp
<div class="row">
    @foreach ([
        ['Total collected', '₹'.number_format($stats['collected'], 2), 'fa-rupee-sign', 'success', ['status' => 'success']],
        ['Successful payments', number_format($stats['success']), 'fa-check-circle', 'primary', ['status' => 'success']],
        ['Collected today', '₹'.number_format($stats['today'], 2), 'fa-calendar-day', 'info', ['status' => 'success', 'from' => now('Asia/Kolkata')->toDateString()]],
        ['Unconfirmed > '.config('payment.status_check_after_minutes').' min', number_format($stats['unconfirmed']), 'fa-hourglass-half', 'warning', ['status' => 'unconfirmed']],
        ['Failed', number_format($stats['failed']), 'fa-times-circle', 'danger', ['status' => 'failed']],
        ['Duplicates (refund)', number_format($stats['duplicates']), 'fa-clone', 'secondary', ['duplicates' => 1]],
    ] as [$label, $value, $icon, $color, $filter])
        <div class="col-sm-6 col-md-4 col-xl-2">
            <a href="{{ route('admin.payments.index', $filter) }}" class="text-decoration-none">
                <div class="card card-stats card-round">
                    <div class="card-body py-3">
                        <p class="card-category mb-1"><i class="fas {{ $icon }} text-{{ $color }} me-1"></i>{{ $label }}</p>
                        <h5 class="card-title mb-0">{{ $value }}</h5>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Order, receipt, SBI ref, app no, CUET no, email, name"></div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">Any status</option>
                    @foreach (['success' => 'Successful', 'unconfirmed' => 'Unconfirmed (initiated / pending)', 'pending' => 'Pending', 'initiated' => 'Initiated', 'failed' => 'Failed'] as $k => $v)
                        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="purpose">
                    <option value="">Any purpose</option>
                    @foreach ($purposes as $k => $v)<option value="{{ $k }}" @selected(request('purpose') === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><input type="date" class="form-control" name="from" value="{{ request('from') }}" title="From"></div>
            <div class="col-md-2"><input type="date" class="form-control" name="to" value="{{ request('to') }}" title="To"></div>
            <div class="col-12"><button class="btn btn-primary btn-sm">Filter</button> <a href="{{ route('admin.payments.index') }}" class="btn btn-link btn-sm">Clear</a></div>
            @if (request('duplicates'))<input type="hidden" name="duplicates" value="1">@endif
        </form>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <form method="POST" action="{{ route('admin.payments.check-pending') }}">
                @csrf
                <button class="btn btn-warning btn-sm" type="submit"><i class="fas fa-sync me-1"></i>Check all unconfirmed with SBI</button>
            </form>
            <a class="btn btn-success btn-sm" href="{{ route('admin.payments.export', request()->query()) }}"><i class="fas fa-file-excel me-1"></i>Export to Excel (CSV)</a>
            <span class="small text-muted align-self-center">Unconfirmed payments are also re-checked automatically every 15 minutes, from {{ config('payment.status_check_after_minutes') }} minutes to {{ config('payment.reconcile_max_hours') }} hours after the attempt.</span>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
                <thead><tr><th>Initiated (IST)</th><th>Order no.</th><th>Applicant</th><th>Purpose</th><th class="text-end">Amount</th><th>Status</th><th>Receipt</th><th></th></tr></thead>
                <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td class="text-nowrap small">{{ $p->created_at->timezone('Asia/Kolkata')->format('d M Y h:i A') }}</td>
                        <td><a href="{{ route('admin.payments.show', $p) }}">{{ $p->order_no }}</a>@if ($p->gateway_ref)<br><span class="small text-muted">SBI {{ $p->gateway_ref }}</span>@endif</td>
                        <td class="small">{{ $p->application?->user?->name }}<br><span class="text-muted">{{ $p->application?->application_no }}</span></td>
                        <td class="small">{{ $p->purposeLabel() }}</td>
                        <td class="text-end">₹{{ number_format((float) $p->amount, 2) }}</td>
                        <td>
                            <span class="badge badge-{{ $badge[$p->status] ?? 'secondary' }}">{{ ucfirst($p->status) }}</span>
                            @if ($p->is_duplicate)<span class="badge badge-warning">Duplicate</span>@endif
                        </td>
                        <td class="small">@if ($p->receipt_no)<a href="{{ route('admin.payments.receipt', $p) }}" target="_blank" rel="noopener">{{ $p->receipt_no }}</a>@else – @endif</td>
                        <td>
                            @unless ($p->isSuccess())
                                <form method="POST" action="{{ route('admin.payments.check', $p) }}">
                                    @csrf
                                    <button class="btn btn-outline-primary btn-sm text-nowrap" type="submit"><i class="fas fa-sync"></i> Check with SBI</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">No payments found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $payments->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
