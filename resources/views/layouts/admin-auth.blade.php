<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Staff Login') | UG Admissions 2027</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/fonts.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/kaiadmin.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/ssc-admin.css') }}">
</head>
<body>
<div class="admin-auth p-3">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <img src="{{ asset('images/logo.png') }}" alt="St. Stephen's College" style="height:56px">
                <h5 class="mt-3 mb-0">@yield('heading', 'Staff Login')</h5>
                <small class="text-muted">UG Admissions 2027</small>
            </div>
            @if (session('status'))<div class="alert alert-success py-2">{{ session('status') }}</div>@endif
            @yield('content')
        </div>
    </div>
</div>
<script src="{{ asset('admin-assets/js/ssc-admin.js') }}"></script>
</body>
</html>
