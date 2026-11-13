@extends('layouts.frontend')
@section('title', 'Send a Query')
@section('page_title', 'Admissions Helpdesk')
@section('content')
<div class="container" style="max-width: 760px">
    @if ($user?->hasVerifiedEmail())
        <div class="alert alert-info">You are logged in. You can also <a href="{{ route('help.chat') }}">chat with the admissions team</a>.</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ route('help.query.send') }}" enctype="multipart/form-data" class="p-4 border">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Name <span class="req">*</span></label>
                <input class="form-control" id="name" name="name" maxlength="100" value="{{ old('name', $user?->name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email <span class="req">*</span></label>
                <input type="email" class="form-control" id="email" name="email" maxlength="150" value="{{ old('email', $user?->email) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="mobile">Mobile</label>
                <input class="form-control" id="mobile" name="mobile" maxlength="10" inputmode="numeric" data-digits-only value="{{ old('mobile', $user?->mobile) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="subject">Subject <span class="req">*</span></label>
                <input class="form-control" id="subject" name="subject" maxlength="150" value="{{ old('subject') }}" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="message">Your query <span class="req">*</span></label>
                <textarea class="form-control" id="message" name="message" rows="5" maxlength="3000" required>{{ old('message') }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="attachment">Screenshot (optional)</label>
                <input type="file" class="form-control" id="attachment" name="attachment" accept=".jpg,.jpeg,.png">
                <div class="form-text">JPG or PNG, up to 2 MB.</div>
            </div>
            <div class="col-md-6">@include('partials.captcha')</div>
        </div>
        <button class="btn btn-primary py-2 px-4" type="submit">Send query</button>
    </form>
</div>
@endsection
