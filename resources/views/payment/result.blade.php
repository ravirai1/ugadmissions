@extends('layouts.frontend')
@section('title', 'Payment Status')
@section('page_title', 'Payment Status')
@section('content')
<div class="container" style="max-width: 640px">
    <div class="p-4 border text-center">
        @if ($payment->isSuccess())
            <i class="fa fa-check-circle fa-3x text-success mb-3"></i>
            <h4>Payment successful</h4>
            <p>Receipt no. <strong>{{ $payment->receipt_no }}</strong> has been generated and emailed to you.</p>
        @elseif ($payment->status === 'failed')
            <i class="fa fa-times-circle fa-3x text-danger mb-3"></i>
            <h4>Payment not successful</h4>
            <p class="small">{{ $payment->gateway_reason ?: 'The payment was cancelled or declined.' }} If money was debited, it is normally reversed by your bank within 5–7 working days. You can also use "Check payment status" from your dashboard after {{ $waitMinutes }} minutes.</p>
        @else
            <i class="fa fa-hourglass-half fa-3x text-warning mb-3"></i>
            <h4>Awaiting confirmation from SBI</h4>
            <p class="small">We have not received confirmation from SBI ePay yet. <strong>Please do not pay again.</strong> We check automatically, and you can use "Check payment status" on your dashboard after {{ $waitMinutes }} minutes.</p>
        @endif
        <table class="table table-sm small text-start mt-3">
            <tr><th>Order no.</th><td>{{ $payment->order_no }}</td></tr>
            <tr><th>For</th><td>{{ $payment->purposeLabel() }}</td></tr>
            <tr><th>Amount</th><td>₹{{ number_format((float) $payment->amount, 2) }}</td></tr>
            @if ($payment->gateway_ref)<tr><th>SBI ePay ref.</th><td>{{ $payment->gateway_ref }}</td></tr>@endif
        </table>
        <a href="{{ route('payment.index') }}" class="btn btn-primary">Go to payments</a>
    </div>
</div>
@endsection
