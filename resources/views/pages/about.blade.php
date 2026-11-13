@extends('layouts.frontend')
@section('title', 'About')
@section('page_title', 'About')
@section('content')
<div class="container">
    <div class="row g-5 align-items-center">
        <div class="col-lg-6">
            <div class="bg-light p-5 text-center"><img class="img-fluid" src="{{ asset('images/logo.png') }}" alt="St. Stephen's College"></div>
        </div>
        <div class="col-lg-6">
            <h2 class="mb-4">St. Stephen's College</h2>
            <p>Founded in 1881, St. Stephen's College is a constituent college of the University of Delhi and one of the oldest colleges in India. The College is a Christian minority institution with a residential tradition and a strong commitment to academic excellence and community life.</p>
            <p>Undergraduate admissions are made on the basis of CUET (UG) scores as prescribed by the University of Delhi, together with the College's interview, in accordance with the College's admission policy.</p>
            <p class="mb-0 text-muted small">This page's text is a placeholder for the admissions office to replace with official content.</p>
        </div>
    </div>
</div>
@endsection
