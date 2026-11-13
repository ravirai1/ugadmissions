@extends('layouts.admin')
@section('title', 'Payment '.$payment->order_no)
@section('content')
@php
    $ist = fn ($d) => $d?->timezone('Asia/Kolkata')->format('d M Y h:i:s A') ?? '–';
    $u = $payment->application?->user;
@endphp
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="card-title mb-0">Transaction</h5>
                    <div class="d-flex gap-2">
                        @if ($payment->isSuccess())
                            <a class="btn btn-sm btn-primary" href="{{ route('admin.payments.receipt', $payment) }}" target="_blank" rel="noopener"><i class="fas fa-receipt me-1"></i>Receipt</a>
                        @else
                            <form method="POST" action="{{ route('admin.payments.check', $payment) }}">@csrf<button class="btn btn-sm btn-warning" type="submit"><i class="fas fa-sync me-1"></i>Check with SBI</button></form>
                        @endif
                    </div>
                </div>
                <table class="table table-sm">
                    <tr><th>Order no.</th><td>{{ $payment->order_no }}</td></tr>
                    <tr><th>Status</th><td>{{ ucfirst($payment->status) }} @if ($payment->is_duplicate)<span class="badge badge-warning">Duplicate – refund review</span>@endif</td></tr>
                    <tr><th>Purpose</th><td>{{ $payment->purposeLabel() }}</td></tr>
                    <tr><th>Amount</th><td>₹{{ number_format((float) $payment->amount, 2) }}</td></tr>
                    <tr><th>Items</th><td>@foreach ($payment->items as $i){{ $i['label'] }} (₹{{ $i['amount'] }})@if (! $loop->last), @endif @endforeach</td></tr>
                    <tr><th>Receipt no.</th><td>{{ $payment->receipt_no ?: '–' }}</td></tr>
                    <tr><th>SBI ePay ref.</th><td>{{ $payment->gateway_ref ?: '–' }}</td></tr>
                    <tr><th>Bank ref.</th><td>{{ $payment->bank_ref ?: '–' }}</td></tr>
                    <tr><th>Mode</th><td>{{ $payment->pay_mode ?: '–' }}</td></tr>
                    <tr><th>Gateway status</th><td>{{ $payment->gateway_status ?: '–' }} {{ $payment->gateway_reason }}</td></tr>
                    <tr><th>Initiated</th><td>{{ $ist($payment->created_at) }} from {{ $payment->initiated_ip }}</td></tr>
                    <tr><th>Paid</th><td>{{ $ist($payment->paid_at) }}</td></tr>
                    <tr><th>Last checked with SBI</th><td>{{ $ist($payment->last_checked_at) }} ({{ $payment->check_count }} checks)</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Applicant</h5>
                <table class="table table-sm">
                    <tr><th>Name</th><td>{{ $u?->name }}</td></tr>
                    <tr><th>Application no.</th><td>{{ $payment->application?->application_no }}</td></tr>
                    <tr><th>CUET no.</th><td>{{ $u?->cuet_no }}</td></tr>
                    <tr><th>Email</th><td>{{ $u?->email }}</td></tr>
                    <tr><th>Mobile</th><td>{{ $u?->mobile }}</td></tr>
                    <tr><th>Application fee paid</th><td>{{ $ist($payment->application?->fee_paid_at) }}</td></tr>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">History</h5>
                <ul class="list-unstyled small mb-0">
                    @foreach ($payment->events as $e)
                        <li class="border-bottom py-2">
                            <strong>{{ $ist($e->created_at) }}</strong> · {{ str_replace('_', ' ', $e->source) }}
                            @if ($e->status)<span class="badge badge-secondary">{{ $e->status }}</span>@endif
                            @if ($e->admin) by {{ $e->admin->name }}@endif
                            <br><span class="text-muted">{{ $e->detail }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
<a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary btn-sm">Back to payments</a>
@endsection
