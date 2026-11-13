@extends('layouts.frontend')
@section('title', 'Add Course')
@section('page_title', 'Add Course')
@section('content')
<div class="container">
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    <p class="mb-2">Application no. <strong>{{ $app->application_no }}</strong> · <a href="{{ route('payment.index') }}">Back to payments</a></p>
    <p class="small text-muted">You have applied for: {{ $have->map(fn ($c) => $c->course->name.' (DU pref. '.$c->du_preference.')')->implode(', ') }}.
        Each course added now costs <strong>₹{{ $fee }}</strong>. The course is added to your application once SBI ePay confirms the payment.</p>

    <form method="POST" action="{{ route('payment.add-courses.pay') }}" data-app-form data-pay-once novalidate>
        @csrf
        <div class="form-section">
            <h5>Choose courses to add</h5>
            @error('courses')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
            @forelse ($courses as $course)
                @include('application._course-card', ['e' => $evals[$course->id], 'c' => null, 'withStatement' => true])
            @empty
                <p class="text-muted">You have already applied for every course on offer.</p>
            @endforelse
        </div>
        @if ($courses->contains(fn ($c) => $evals[$c->id]['eligible']))
            <button type="submit" class="btn btn-primary py-2 px-4"><i class="fa fa-lock me-2"></i>Continue to pay with SBI ePay</button>
        @endif
    </form>
</div>
@endsection
@push('scripts')
<script src="{{ asset('frontend/js/ssc-form.js') }}"></script>
<script src="{{ asset('frontend/js/ssc-pay.js') }}"></script>
@endpush
