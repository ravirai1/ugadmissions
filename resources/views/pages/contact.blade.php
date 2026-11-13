@extends('layouts.frontend')
@section('title', 'Contact')
@section('page_title', 'Contact')
@section('content')
<div class="container">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="p-4 bg-light h-100 text-center">
                <i class="fa fa-map-marker-alt fa-2x text-primary mb-3"></i>
                <h5>Address</h5>
                <p class="mb-0">{{ config('admissions.contact.address') }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 bg-light h-100 text-center">
                <i class="fa fa-phone-alt fa-2x text-primary mb-3"></i>
                <h5>Phone</h5>
                <p class="mb-0">{{ config('admissions.contact.phone') }}<br><small class="text-muted">Mon–Fri, 9:30 AM – 4:30 PM</small></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 bg-light h-100 text-center">
                <i class="fa fa-envelope fa-2x text-primary mb-3"></i>
                <h5>Email</h5>
                <p class="mb-0">{{ config('admissions.contact.email') }}</p>
            </div>
        </div>
    </div>
    <div class="text-center mt-4">
        <p class="text-muted mb-3">For application queries, send us a query with a screenshot, or log in and chat with the admissions team.</p>
        <a href="{{ route('help.query') }}" class="btn btn-primary py-2 px-4 me-2"><i class="fa fa-question-circle me-2"></i>Send a query</a>
        @auth<a href="{{ route('help.chat') }}" class="btn btn-outline-primary py-2 px-4"><i class="fa fa-comments me-2"></i>Chat with us</a>@endauth
    </div>
</div>
@endsection
