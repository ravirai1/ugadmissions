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
    <p class="text-center text-muted mt-4">For application queries, use the Help button to chat with the admissions team or send a query with a screenshot (available soon).</p>
</div>
@endsection
