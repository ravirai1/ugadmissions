@php
    $app = $payment->application;
    $ist = fn ($d) => $d?->timezone('Asia/Kolkata')->format('d M Y, h:i A');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->receipt_no }} | St. Stephen's College</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="{{ asset('favicon.ico') }}" rel="icon">
    <link href="{{ asset('frontend/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        body { background: #f4f4f4; }
        .receipt { max-width: 760px; margin: 24px auto; background: #fff; padding: 32px; border: 1px solid #ddd; }
        .receipt th { width: 38%; background: #fafafa; }
        .stamp { border: 2px solid #198754; color: #198754; display: inline-block; padding: 4px 14px; font-weight: 700; letter-spacing: 1px; transform: rotate(-4deg); }
        @media print { body { background: #fff; } .no-print { display: none !important; } .receipt { border: 0; margin: 0; max-width: none; } }
    </style>
</head>
<body>
<div class="receipt">
    <div class="d-flex align-items-center border-bottom pb-3 mb-3">
        <img src="{{ asset('images/logo.png') }}" alt="St. Stephen's College" style="height:64px">
        <div class="ms-auto text-end">
            <div class="fw-bold">Fee Receipt</div>
            <div class="small text-muted">UG Admissions 2027</div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><span class="text-muted small">Receipt no.</span><br><strong class="fs-5">{{ $payment->receipt_no }}</strong></div>
        <span class="stamp">PAID</span>
    </div>

    <table class="table table-bordered table-sm small">
        <tr><th>Applicant name</th><td>{{ $app->user->name }}</td></tr>
        <tr><th>Application no.</th><td>{{ $app->application_no }}</td></tr>
        <tr><th>CUET (UG) 2027 application no.</th><td>{{ $app->user->cuet_no }}</td></tr>
        <tr><th>Email / Mobile</th><td>{{ $app->user->email }} / +91 {{ $app->user->mobile }}</td></tr>
        <tr><th>Payment for</th><td>{{ $payment->purposeLabel() }}</td></tr>
        <tr><th>Order no.</th><td>{{ $payment->order_no }}</td></tr>
        <tr><th>SBI ePay reference</th><td>{{ $payment->gateway_ref ?: '–' }}</td></tr>
        <tr><th>Bank reference</th><td>{{ $payment->bank_ref ?: '–' }}</td></tr>
        <tr><th>Payment mode</th><td>{{ $payment->pay_mode ?: '–' }}</td></tr>
        <tr><th>Paid on</th><td>{{ $ist($payment->paid_at) }}</td></tr>
    </table>

    <table class="table table-sm small">
        <thead class="table-light"><tr><th>Particulars</th><th class="text-end">Amount (₹)</th></tr></thead>
        <tbody>
            @foreach ($payment->items as $item)
                <tr><td>{{ $item['label'] }}@if (($item['type'] ?? '') === 'course') (course added after payment)@endif</td><td class="text-end">{{ number_format($item['amount'], 2) }}</td></tr>
            @endforeach
            <tr class="fw-bold"><td>Total paid</td><td class="text-end">{{ number_format((float) $payment->amount, 2) }}</td></tr>
        </tbody>
    </table>

    @if ($payment->is_duplicate)
        <div class="alert alert-warning small py-2">This payment duplicates one already received and is under review by the Accounts office for refund.</div>
    @endif

    <p class="small text-muted mb-0">This is a computer-generated receipt and needs no signature. Fees once paid are non-refundable except for duplicate payments. Generated {{ $ist(now()) }}.</p>

    <div class="no-print mt-4 d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm" data-print>Print / Save as PDF</button>
        <a href="{{ $back }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>
<script src="{{ asset('frontend/js/ssc-pay.js') }}"></script>
</body>
</html>
