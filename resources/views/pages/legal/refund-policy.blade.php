@extends('layouts.frontend')
@section('title', 'Refund & Cancellation Policy')
@section('page_title', 'Refund & Cancellation Policy')
@section('content')
<div class="container">
    @include('pages.legal._draft')
    <ol>
        <li class="mb-2">The application fee (including fees for additional courses and residence) is <strong>non-refundable</strong> once the payment is successful, except as stated below.</li>
        <li class="mb-2">If your account was debited but the payment shows as failed or pending, the College verifies it with SBI ePay. A confirmed payment is updated on your dashboard; a failed transaction is reversed by the bank to the original payment method, usually within 7–10 working days.</li>
        <li class="mb-2">If you are charged twice for the same item, the duplicate amount is refunded to the original payment method after verification. Write to {{ config('admissions.contact.email') }} with your application number and transaction reference.</li>
        <li class="mb-2">Applications cannot be cancelled after payment. Courses or residence added after payment cannot be removed.</li>
        <li class="mb-2">If the College cancels the admission process, refunds will be made as announced by the College.</li>
    </ol>
</div>
@endsection
