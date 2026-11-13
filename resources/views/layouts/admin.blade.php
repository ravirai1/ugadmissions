@php($me = auth('admin')->user())
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | UG Admissions 2027 Staff</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/fonts.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/plugins.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/kaiadmin.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/ssc-admin.css') }}">
</head>
<body>
<div class="wrapper">
    <div class="sidebar" data-background-color="dark">
        <div class="sidebar-logo">
            <div class="logo-header" data-background-color="dark">
                <a href="{{ route('admin.dashboard') }}" class="logo">
                    <img src="{{ asset('images/crest.png') }}" alt="St. Stephen's College" class="navbar-brand">
                    <span class="text-white fw-bold ms-2">SSC Admissions</span>
                </a>
                <div class="nav-toggle">
                    <button class="btn btn-toggle toggle-sidebar" aria-label="Toggle sidebar"><i class="gg-menu-right"></i></button>
                    <button class="btn btn-toggle sidenav-toggler" aria-label="Toggle sidebar"><i class="gg-menu-left"></i></button>
                </div>
                <button class="topbar-toggler more" aria-label="More"><i class="gg-more-vertical-alt"></i></button>
            </div>
        </div>
        <div class="sidebar-wrapper scrollbar scrollbar-inner">
            <div class="sidebar-content">
                <ul class="nav nav-secondary">
                    <li class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <a href="{{ route('admin.dashboard') }}"><i class="fas fa-home"></i><p>Dashboard</p></a>
                    </li>
                    @if ($me->isSuperAdmin())
                        <li class="nav-section"><span class="sidebar-mini-icon"><i class="fa fa-ellipsis-h"></i></span><h4 class="text-section">Security</h4></li>
                        <li class="nav-item {{ request()->routeIs('admin.security.attempts') ? 'active' : '' }}">
                            <a href="{{ route('admin.security.attempts') }}"><i class="fas fa-sign-in-alt"></i><p>Login Attempts</p></a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('admin.security.blocked') ? 'active' : '' }}">
                            <a href="{{ route('admin.security.blocked') }}"><i class="fas fa-ban"></i><p>Blocked IPs</p></a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('admin.security.audit') ? 'active' : '' }}">
                            <a href="{{ route('admin.security.audit') }}"><i class="fas fa-history"></i><p>Audit Log</p></a>
                        </li>
                    @endif
                    <li class="nav-section"><span class="sidebar-mini-icon"><i class="fa fa-ellipsis-h"></i></span><h4 class="text-section">Account</h4></li>
                    <li class="nav-item {{ request()->routeIs('admin.password.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.password.edit') }}"><i class="fas fa-key"></i><p>Change Password</p></a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="main-panel">
        <div class="main-header">
            <div class="main-header-logo">
                <div class="logo-header" data-background-color="dark">
                    <a href="{{ route('admin.dashboard') }}" class="logo"><img src="{{ asset('images/crest.png') }}" alt="" class="navbar-brand"></a>
                    <div class="nav-toggle">
                        <button class="btn btn-toggle toggle-sidebar" aria-label="Toggle sidebar"><i class="gg-menu-right"></i></button>
                        <button class="btn btn-toggle sidenav-toggler" aria-label="Toggle sidebar"><i class="gg-menu-left"></i></button>
                    </div>
                    <button class="topbar-toggler more" aria-label="More"><i class="gg-more-vertical-alt"></i></button>
                </div>
            </div>
            <nav class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
                <div class="container-fluid">
                    <span class="fw-bold d-none d-lg-inline">St. Stephen's College · UG Admissions 2027</span>
                    <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">
                        <li class="nav-item topbar-user dropdown hidden-caret">
                            <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                                <span class="profile-username"><span class="op-7">{{ $me->roleLabel() }}:</span> <span class="fw-bold">{{ $me->name }}</span></span>
                            </a>
                            <ul class="dropdown-menu dropdown-user animated fadeIn">
                                <li>
                                    <div class="dropdown-item small text-muted">{{ $me->email }}@if ($me->department)<br>{{ $me->department->name }}@endif</div>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="{{ route('admin.password.edit') }}">Change Password</a>
                                    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="dropdown-item" type="submit">Logout</button></form>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
        </div>

        <div class="container">
            <div class="page-inner">
                <div class="page-header"><h4 class="page-title">@yield('title', 'Dashboard')</h4></div>
                @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
                @if ($errors->any() && ! View::hasSection('suppress_errors'))
                    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif
                @yield('content')
            </div>
        </div>

        <footer class="footer">
            <div class="container-fluid d-flex justify-content-between">
                <div class="copyright">&copy; {{ date('Y') }} St. Stephen's College</div>
                <div class="small">Admin theme: <a href="https://www.themekita.com" rel="noopener">ThemeKita</a></div>
            </div>
        </footer>
    </div>
</div>
<script src="{{ asset('admin-assets/js/core/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('admin-assets/js/core/popper.min.js') }}"></script>
<script src="{{ asset('admin-assets/js/core/bootstrap.min.js') }}"></script>
<script src="{{ asset('admin-assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js') }}"></script>
<script src="{{ asset('admin-assets/js/kaiadmin.min.js') }}"></script>
<script src="{{ asset('admin-assets/js/ssc-admin.js') }}"></script>
@stack('scripts')
</body>
</html>
