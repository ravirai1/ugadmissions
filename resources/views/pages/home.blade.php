@extends('layouts.frontend')
@section('title', 'Home')
@section('content')
@php $fees = \App\Services\FeeCalculator::fees(); @endphp
<div class="container-fluid hero d-flex align-items-center mb-5">
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8">
                <p class="text-uppercase fw-bold mb-2" style="color: var(--ssc-gold)">University of Delhi</p>
                <h1 class="display-4 text-white mb-4">Undergraduate Admissions 2027</h1>
                <p class="fs-5 text-white-50 mb-4">Apply to St. Stephen's College through CUET (UG) 2027. Register with your CUET application number, complete the online form, upload documents and pay the application fee online.</p>
                @auth('web')
                    <a href="{{ route('applicant.dashboard') }}" class="btn btn-primary py-3 px-5 me-3">Go to Dashboard</a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary py-3 px-5 me-3">Apply Now</a>
                    <a href="{{ route('login') }}" class="btn btn-light py-3 px-5">Login</a>
                @endauth
            </div>
            <div class="col-lg-4 d-none d-lg-flex align-items-center justify-content-center">
                <div class="hero-crest-wrap"><img src="{{ asset('images/crest.png') }}" alt="" class="hero-crest"></div>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    <div class="text-center mb-5">
        <h2 class="text-uppercase">How to Apply</h2>
        <p class="text-muted">Five simple steps</p>
    </div>
    <div class="row g-4">
        @foreach ([
            ['fa-user-plus', 'Register', 'Sign up with your name, email, mobile number and CUET 2027 application number. Verify your email.'],
            ['fa-edit', 'Fill the Form', 'Personal details, category, Class 10 & 12 marks, CUET scores, course choices and statements.'],
            ['fa-upload', 'Upload Documents', 'Photograph, signature and certificates in JPG/PNG or PDF format.'],
            ['fa-rupee-sign', 'Pay Online', 'Pay the application fee securely through SBI ePay.'],
            ['fa-clipboard-check', 'Track Status', 'Follow scrutiny, interview call letters and results on your dashboard.'],
        ] as $i => [$icon, $title, $text])
            <div class="col-lg col-md-6">
                <div class="h-100 p-4 bg-light text-center">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-3" style="width:64px;height:64px">
                        <i class="fa {{ $icon }} fa-lg"></i>
                    </div>
                    <h5 class="mb-2">{{ $i + 1 }}. {{ $title }}</h5>
                    <p class="mb-0 small text-muted">{{ $text }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mt-4">
        <div class="col-lg-6">
            <div class="p-4 border h-100">
                <h4 class="mb-3"><i class="fa fa-calendar-alt text-primary me-2"></i>Important Dates</h4>
                <p class="text-muted small">Dates will be announced here after the CUET (UG) 2027 schedule is released.</p>
                <ul class="list-unstyled mb-0">
                    <li class="py-2 border-bottom">Registration opens: <strong>To be announced</strong></li>
                    <li class="py-2 border-bottom">Last date to submit &amp; pay: <strong>To be announced</strong></li>
                    <li class="py-2">Interviews: <strong>To be announced</strong></li>
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="p-4 border h-100">
                <h4 class="mb-3"><i class="fa fa-rupee-sign text-primary me-2"></i>Application Fee</h4>
                <ul class="list-unstyled mb-0">
                    <li class="py-2 border-bottom">First course: <strong>₹{{ $fees['first_course'] }}</strong></li>
                    <li class="py-2 border-bottom">Each additional course: <strong>₹{{ $fees['additional_course'] }}</strong></li>
                    <li class="py-2 border-bottom">Residence (optional): <strong>₹{{ number_format($fees['residence']) }}</strong></li>
                    <li class="py-2">Course added after payment: <strong>₹{{ $fees['course_after_payment'] }}</strong> per course</li>
                </ul>
            </div>
        </div>
    </div>
    @if (\App\Support\Prospectus::exists())
        <div class="p-4 border mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><h4 class="mb-1"><i class="fa fa-book-open text-primary me-2"></i>Prospectus</h4><span class="text-muted">Read the admissions prospectus online, page by page, or download it.</span></div>
            <a href="{{ route('prospectus') }}" class="btn btn-primary">Read the prospectus</a>
        </div>
    @endif
    @if ($notices->isNotEmpty())
        <div class="p-4 border mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fa fa-bullhorn text-primary me-2"></i>Notices</h4>
                <a href="{{ route('notices') }}" class="small">All notices</a>
            </div>
            <ul class="list-unstyled mb-0">
                @foreach ($notices as $n)
                    <li class="py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                        <a href="{{ route('notices') }}#notice-{{ $n->id }}">@if ($n->is_pinned)<i class="fa fa-thumbtack me-1"></i>@endif{{ $n->title }}</a>
                        <span class="small text-muted ms-2">{{ $n->published_at?->timezone('Asia/Kolkata')->format('d M Y') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
