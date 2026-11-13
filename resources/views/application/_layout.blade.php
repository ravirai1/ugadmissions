@extends('layouts.frontend')
@section('title', 'Application – '.\App\Support\ApplicationSteps::STEPS[$step])
@section('content')
<div class="container mt-4">
    <div class="row g-4">
        <div class="col-lg-3">
            <div class="list-group step-nav mb-3">
                @foreach (\App\Support\ApplicationSteps::STEPS as $slug => $label)
                    @php
                        $done = $app->hasCompleted($slug);
                        $open = $loop->first || collect(array_slice(\App\Support\ApplicationSteps::slugs(), 0, $loop->index))->every(fn ($s) => $app->hasCompleted($s));
                    @endphp
                    <a href="{{ route('application.step', $slug) }}"
                       class="list-group-item list-group-item-action {{ $slug === $step ? 'active' : '' }} {{ $done ? 'done' : '' }} {{ ! $open || ($app->isSubmitted() && $slug !== 'preview') ? 'locked' : '' }}">
                        <i class="fa {{ $done ? 'fa-check-circle' : 'fa-circle' }} me-2"></i>{{ $loop->iteration }}. {{ $label }}
                    </a>
                @endforeach
            </div>
            <a href="{{ route('applicant.dashboard') }}" class="btn btn-outline-secondary btn-sm w-100"><i class="fa fa-arrow-left me-1"></i>Dashboard</a>
        </div>
        <div class="col-lg-9">
            <h3 class="mb-1">{{ \App\Support\ApplicationSteps::STEPS[$step] }}</h3>
            <p class="text-muted small mb-4">Step {{ \App\Support\ApplicationSteps::index($step) + 1 }} of {{ count(\App\Support\ApplicationSteps::STEPS) }} · Your progress is saved when you click "Save &amp; Continue". Fields marked <span class="text-primary">*</span> are required.</p>
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Please correct the following:</strong>
                    <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('step')
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('frontend/js/ssc-form.js') }}"></script>
@endpush
