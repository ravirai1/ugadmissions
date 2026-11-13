@extends('layouts.frontend')
@section('title', 'Query on your application')
@section('page_title', 'Query on Your Application')
@section('content')
<div class="container" style="max-width: 760px">
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    <div class="p-4 border mb-4">
        <p class="small text-muted mb-1">Application {{ $q->application->application_no }} · {{ $q->application->user->name }} · raised {{ $q->created_at->timezone('Asia/Kolkata')->format('d M Y') }}</p>
        <h5 class="mb-2">Message from the scrutiny team</h5>
        <p style="white-space: pre-line" class="mb-2">{{ $q->message }}</p>
        @if ($q->document_types)
            <p class="small mb-0">Documents asked for: <strong>{{ collect($q->document_types)->map(fn ($t) => \App\Support\DocumentTypes::label($t))->implode(', ') }}</strong></p>
        @endif
    </div>

    @if ($action)
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="p-4 border">
            @csrf
            @foreach ($q->document_types ?? [] as $t)
                <div class="mb-3">
                    <label class="form-label" for="f-{{ $t }}">{{ \App\Support\DocumentTypes::label($t) }} <span class="req">*</span></label>
                    <input type="file" class="form-control @error('files.'.$t) is-invalid @enderror" id="f-{{ $t }}" name="files[{{ $t }}]" accept=".{{ implode(',.', \App\Support\DocumentTypes::exts($t)) }}" required>
                    <div class="form-text">{{ strtoupper(implode(', ', \App\Support\DocumentTypes::exts($t))) }}, up to {{ \App\Support\DocumentTypes::maxKb($t) }} KB. It replaces the earlier upload on your application.</div>
                </div>
            @endforeach
            <div class="mb-3">
                <label class="form-label" for="reply">Your reply {{ $q->document_types ? '(optional)' : '' }}</label>
                <textarea class="form-control" id="reply" name="reply" rows="4" maxlength="2000">{{ old('reply') }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="extra">Any other document (optional, up to 3)</label>
                <input type="file" class="form-control" id="extra" name="extra[]" multiple accept=".jpg,.jpeg,.png,.pdf">
                <div class="form-text">JPG, PNG or PDF, up to 2 MB each.</div>
            </div>
            <button type="submit" class="btn btn-primary px-4">Send reply</button>
        </form>
    @else
        <div class="alert alert-info">This query is {{ $q->status }}.@if ($q->replied_at) You replied on {{ $q->replied_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}.@endif</div>
    @endif
</div>
@endsection
