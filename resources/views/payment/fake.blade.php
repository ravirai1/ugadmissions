@extends('layouts.frontend')
@section('title', 'Test Payment Gateway')
@section('content')
<div class="container py-5" style="max-width: 640px">
    <div class="alert alert-warning"><strong>Test gateway.</strong> This page stands in for SBI ePay until the College's merchant credentials are configured. It is never available in production.</div>
    <div class="p-4 border">
        <h4 class="mb-3">Pay ₹{{ number_format((float) $payment->amount, 2) }}</h4>
        <p class="small text-muted">Order no. {{ $payment->order_no }} · {{ $payment->purposeLabel() }}</p>
        @foreach (['success' => ['btn-success', 'Pay (success)'], 'failed' => ['btn-danger', 'Decline (failure)'], 'timeout' => ['btn-secondary', 'Money debited, browser never returns']] as $outcome => [$cls, $label])
            <form method="POST" action="{{ route('payment.fake.complete') }}" class="d-inline-block me-2 mb-2">
                @csrf
                <input type="hidden" name="order_no" value="{{ $payment->order_no }}">
                <input type="hidden" name="sig" value="{{ $sig }}">
                <input type="hidden" name="outcome" value="{{ $outcome }}">
                <button type="submit" class="btn {{ $cls }}">{{ $label }}</button>
            </form>
        @endforeach
    </div>
</div>
@endsection
