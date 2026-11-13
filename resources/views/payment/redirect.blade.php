@extends('layouts.frontend')
@section('title', $title)
@section('content')
<div class="container py-5 text-center">
    <div class="spinner-border text-primary mb-3" role="status"></div>
    <h4>{{ $title }}…</h4>
    @isset($payment)
        <p class="text-muted">Order no. {{ $payment->order_no }} · Amount ₹{{ number_format((float) $payment->amount, 2) }}</p>
    @endisset
    <p class="small text-muted">Please do not refresh or close this window.</p>
    <form method="POST" action="{{ $action }}" data-autosubmit>
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <button type="submit" class="btn btn-primary mt-2">Continue</button>
    </form>
</div>
@endsection
@push('scripts')
<script src="{{ asset('frontend/js/ssc-pay.js') }}"></script>
@endpush
