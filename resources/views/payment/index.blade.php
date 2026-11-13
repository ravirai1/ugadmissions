@extends('layouts.frontend')
@section('title', 'Fee Payment')
@section('page_title', 'Fee Payment')
@section('content')
@php
    $recent = $payments->first(fn ($p) => ! $p->isSuccess() && $p->status !== 'failed' && ! $p->canCheckStatus());
@endphp
<div class="container">
    @error('payment')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <p class="mb-4">Application no. <strong>{{ $app->application_no }}</strong> · <a href="{{ route('applicant.dashboard') }}">Back to dashboard</a></p>

    <div class="row g-4">
        <div class="col-lg-7">
            @if ($fee)
                <div class="p-4 border mb-4">
                    <h5 class="mb-3">Application fee</h5>
                    <table class="table table-sm mb-3">
                        @foreach ($fee['lines'] as $line)
                            <tr><td>{{ $line['label'] }}</td><td class="text-end">₹{{ number_format($line['amount']) }}</td></tr>
                        @endforeach
                        <tr class="fw-bold"><td>Total</td><td class="text-end">₹{{ number_format($fee['total']) }}</td></tr>
                    </table>
                    <p class="small text-muted">₹{{ $fees['first_course'] }} for the first course, ₹{{ $fees['additional_course'] }} for each additional course, ₹{{ number_format($fees['residence']) }} for residence. Fees are non-refundable.</p>
                    @if ($recent)
                        <div class="alert alert-warning small">You started a payment at {{ $recent->created_at->timezone('Asia/Kolkata')->format('h:i A') }}. If money was debited from your account, <strong>do not pay again</strong>. Check its status below from {{ $recent->checkAvailableAt()->timezone('Asia/Kolkata')->format('h:i A') }}.</div>
                    @endif
                    <form method="POST" action="{{ route('payment.application') }}" data-pay-once>
                        @csrf
                        <button type="submit" class="btn btn-primary py-2 px-4"><i class="fa fa-lock me-2"></i>Pay ₹{{ number_format($fee['total']) }} with SBI ePay</button>
                    </form>
                </div>
            @else
                <div class="p-4 border mb-4">
                    <h5 class="mb-2"><i class="fa fa-check-circle text-success me-2"></i>Application fee paid</h5>
                    <p class="small mb-0">Paid on {{ $app->fee_paid_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}. You can still add courses or residence below.</p>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="p-4 bg-light h-100">
                            <h6>Add a course</h6>
                            <p class="small text-muted">₹{{ $fees['course_after_payment'] }} for each course added after payment. Only courses you are eligible for are shown.</p>
                            <a href="{{ route('payment.add-courses') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-plus me-1"></i>Add course</a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-4 bg-light h-100">
                            <h6>Residence</h6>
                            @if ($app->residence)
                                <p class="small mb-0"><i class="fa fa-check text-success me-1"></i>You have applied for College residence.</p>
                            @else
                                <p class="small text-muted">Apply for College residence for ₹{{ number_format($fees['residence_after_payment']) }}.</p>
                                <form method="POST" action="{{ route('payment.add-residence') }}" data-pay-once>
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fa fa-home me-1"></i>Pay ₹{{ number_format($fees['residence_after_payment']) }} for residence</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="p-4 border">
                <h5 class="mb-3">Payment history</h5>
                @include('payment._history')
            </div>
        </div>
        <div class="col-lg-5">
            <div class="p-4 bg-light">
                <h5 class="mb-3">Courses applied</h5>
                <ul class="small mb-3">
                    @foreach ($app->courses as $c)
                        <li>{{ $c->course->name }} (DU pref. {{ $c->du_preference }})
                            @if ($c->added_after_payment)<span class="badge bg-info text-dark">added later</span>@endif
                        </li>
                    @endforeach
                    @if ($app->residence)<li>Residence</li>@endif
                </ul>
                <h6>About online payment</h6>
                <ul class="small text-muted mb-0">
                    <li>You will be taken to the SBI ePay page to pay by net banking, card or UPI.</li>
                    <li>Do not press Back or close the window until you return to this site.</li>
                    <li>A receipt is generated as soon as SBI confirms the payment and is emailed to you.</li>
                    <li>If the status is unclear, use <strong>Check payment status</strong> {{ $waitMinutes }} minutes after paying.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('frontend/js/ssc-pay.js') }}"></script>
@endpush
