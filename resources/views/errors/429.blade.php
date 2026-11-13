@extends('layouts.frontend')
@section('title', 'Too Many Attempts')
@section('page_title', 'Please Wait')
@section('content')
<div class="container text-center">
    <i class="fa fa-hourglass-half fa-3x text-primary mb-3"></i>
    <p class="lead">{{ $exception->getMessage() ?: 'Too many requests. Please wait a few minutes and try again.' }}</p>
    <a href="{{ route('home') }}" class="btn btn-primary">Go to Home</a>
</div>
@endsection
