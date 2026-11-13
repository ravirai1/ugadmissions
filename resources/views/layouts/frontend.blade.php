<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'UG Admissions 2027') | St. Stephen's College</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="description" content="St. Stephen's College, University of Delhi – Undergraduate Admissions 2027">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ asset('favicon.ico') }}" rel="icon">
    <link href="{{ asset('images/apple-touch-icon.png') }}" rel="apple-touch-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Roboto:wght@700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('admin-assets/css/fonts.min.css') }}" rel="stylesheet">
    <link href="{{ asset('frontend/lib/animate/animate.min.css') }}" rel="stylesheet">
    <link href="{{ asset('frontend/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('frontend/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('frontend/css/ssc.css') }}" rel="stylesheet">
</head>
<body>
    <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
    </div>

    <div class="container-fluid bg-primary text-white d-none d-lg-flex">
        <div class="container py-2">
            <div class="d-flex align-items-center">
                <small><i class="fa fa-graduation-cap me-2"></i>Undergraduate Admissions 2027</small>
                <div class="ms-auto d-flex align-items-center">
                    <small class="ms-4"><i class="fa fa-envelope me-2"></i>{{ config('admissions.contact.email') }}</small>
                    <small class="ms-4"><i class="fa fa-phone-alt me-2"></i>{{ config('admissions.contact.phone') }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid bg-white sticky-top">
        <div class="container">
            <nav class="navbar navbar-expand-lg bg-white navbar-light p-lg-0">
                <a href="{{ route('home') }}" class="navbar-brand ssc-brand py-2">
                    <img src="{{ asset('images/logo.png') }}" alt="St. Stephen's College, University of Delhi">
                </a>
                <button type="button" class="navbar-toggler me-0" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-label="Menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav ms-auto">
                        <a href="{{ route('home') }}" class="nav-item nav-link {{ request()->routeIs('home') ? 'active' : '' }}">Home</a>
                        <a href="{{ route('about') }}" class="nav-item nav-link {{ request()->routeIs('about') ? 'active' : '' }}">About</a>
                        <a href="{{ route('documents') }}" class="nav-item nav-link {{ request()->routeIs('documents') ? 'active' : '' }}">Documents Required</a>
                        <a href="{{ route('faq') }}" class="nav-item nav-link {{ request()->routeIs('faq') ? 'active' : '' }}">FAQ</a>
                        <a href="{{ route('contact') }}" class="nav-item nav-link {{ request()->routeIs('contact') ? 'active' : '' }}">Contact</a>
                    </div>
                    <div class="ms-lg-4 py-2">
                        @auth('web')
                            <a href="{{ route('applicant.dashboard') }}" class="btn btn-primary py-2 px-3">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-outline-primary py-2 px-3 me-2">Login</a>
                            <a href="{{ route('register') }}" class="btn btn-primary py-2 px-3">Register</a>
                        @endauth
                    </div>
                </div>
            </nav>
        </div>
    </div>

    @hasSection('page_title')
        <div class="container-fluid page-header py-5 mb-5">
            <div class="container text-center py-4">
                <h1 class="display-6 text-white text-uppercase mb-2">@yield('page_title')</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center mb-0">
                        <li class="breadcrumb-item"><a class="text-white" href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item text-white-50" aria-current="page">@yield('page_title')</li>
                    </ol>
                </nav>
            </div>
        </div>
    @endif

    <main>
        @include('partials.flash')
        @yield('content')
    </main>

    <div class="container-fluid bg-dark footer mt-5 py-5">
        <div class="container py-4">
            <div class="row g-5">
                <div class="col-lg-4 col-md-6">
                    <h5 class="text-uppercase text-light mb-4">Admissions Office</h5>
                    <p class="mb-2 text-white-50"><i class="fa fa-map-marker-alt text-white me-3"></i>{{ config('admissions.contact.address') }}</p>
                    <p class="mb-2 text-white-50"><i class="fa fa-phone-alt text-white me-3"></i>{{ config('admissions.contact.phone') }}</p>
                    <p class="mb-2 text-white-50"><i class="fa fa-envelope text-white me-3"></i>{{ config('admissions.contact.email') }}</p>
                </div>
                <div class="col-lg-4 col-md-6">
                    <h5 class="text-uppercase text-light mb-4">Quick Links</h5>
                    <a class="btn btn-link" href="{{ route('about') }}">About</a>
                    <a class="btn btn-link" href="{{ route('documents') }}">Documents Required</a>
                    <a class="btn btn-link" href="{{ route('faq') }}">FAQ</a>
                    <a class="btn btn-link" href="{{ route('contact') }}">Contact</a>
                </div>
                <div class="col-lg-4 col-md-6">
                    <h5 class="text-uppercase text-light mb-4">Applicants</h5>
                    <a class="btn btn-link" href="{{ route('register') }}">Create Account</a>
                    <a class="btn btn-link" href="{{ route('login') }}">Login</a>
                    <a class="btn btn-link" href="{{ route('password.request') }}">Forgot Password</a>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid text-body copyright py-3">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    &copy; {{ date('Y') }} St. Stephen's College, University of Delhi. All rights reserved.
                </div>
                <div class="col-md-6 text-center text-md-end small">
                    {{-- Required by the free Weldork template licence (CC BY 4.0). --}}
                    Designed By <a class="fw-semi-bold" href="https://htmlcodex.com" rel="noopener">HTML Codex</a>
                </div>
            </div>
        </div>
    </div>

    <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top" aria-label="Back to top"><i class="fa fa-arrow-up"></i></a>

    <script src="{{ asset('admin-assets/js/core/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('admin-assets/js/core/popper.min.js') }}"></script>
    <script src="{{ asset('admin-assets/js/core/bootstrap.min.js') }}"></script>
    <script src="{{ asset('frontend/lib/wow/wow.min.js') }}"></script>
    <script src="{{ asset('frontend/lib/easing/easing.min.js') }}"></script>
    <script src="{{ asset('frontend/lib/waypoints/waypoints.min.js') }}"></script>
    <script src="{{ asset('frontend/lib/counterup/counterup.min.js') }}"></script>
    <script src="{{ asset('frontend/lib/owlcarousel/owl.carousel.min.js') }}"></script>
    <script src="{{ asset('frontend/js/main.js') }}"></script>
    <script src="{{ asset('frontend/js/ssc.js') }}"></script>
    @stack('scripts')
</body>
</html>
