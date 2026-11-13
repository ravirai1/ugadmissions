@extends('application._layout')
@section('step')
@if ($app->isSubmitted())
    <div class="alert alert-success">Application <strong>{{ $app->application_no }}</strong> was submitted on {{ $app->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}. It can no longer be edited.</div>
@elseif ($missing)
    <div class="alert alert-warning"><strong>Before you can submit:</strong><ul class="mb-0">@foreach ($missing as $m)<li>{{ $m }}</li>@endforeach</ul></div>
@endif

@include('application._details', ['docUrl' => fn ($d) => route('application.document', $d)])

@unless ($app->isSubmitted())
    <form method="POST" action="{{ route('application.submit') }}" class="form-section">
        @csrf
        <div class="form-check mb-3">
            <input class="form-check-input @error('declaration') is-invalid @enderror" type="checkbox" id="declaration" name="declaration" value="1">
            <label class="form-check-label" for="declaration">I declare that the information given in this application is true and complete. I understand that the form cannot be edited after submission and that false information will lead to cancellation of my application or admission.</label>
        </div>
        <div class="d-flex justify-content-between">
            <a href="{{ route('application.step', 'documents') }}" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i>Back</a>
            <button type="submit" class="btn btn-primary px-4" @disabled($missing)>Final Submit</button>
        </div>
    </form>
@endunless
@endsection
