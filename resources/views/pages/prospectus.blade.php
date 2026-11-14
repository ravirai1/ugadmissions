@extends('layouts.frontend')
@section('title', $prospectus['title'])
@section('page_title', 'Prospectus')
@section('content')
<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h4 class="mb-0">{{ $prospectus['title'] }}</h4>
        <a href="{{ route('prospectus.file', ['download' => 1]) }}" class="btn btn-primary"><i class="fa fa-download me-2"></i>Download PDF</a>
    </div>
    <div class="flipbook-wrap" id="flipbook-wrap" data-pdf="{{ route('prospectus.file') }}" data-worker="{{ asset('vendor/pdfjs/pdf.worker.min.js') }}">
        <div class="flipbook-status" id="flipbook-status"><i class="fa fa-spinner fa-spin me-2"></i>Loading the prospectus…</div>
        <div class="flipbook" id="flipbook"></div>
        <div class="flipbook-bar d-none" id="flipbook-bar">
            <button type="button" class="btn btn-light btn-sm" id="fb-first" aria-label="First page"><i class="fa fa-angle-double-left"></i></button>
            <button type="button" class="btn btn-light btn-sm" id="fb-prev" aria-label="Previous page"><i class="fa fa-angle-left"></i> Previous</button>
            <span id="fb-page">1</span>
            <button type="button" class="btn btn-light btn-sm" id="fb-next" aria-label="Next page">Next <i class="fa fa-angle-right"></i></button>
            <button type="button" class="btn btn-light btn-sm" id="fb-last" aria-label="Last page"><i class="fa fa-angle-double-right"></i></button>
        </div>
    </div>
    <p class="small text-muted mt-2">Click or drag a page corner to turn it, or use the arrow keys. If the book does not load, <a href="{{ route('prospectus.file') }}" target="_blank" rel="noopener">open the PDF</a>.</p>
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
<script src="{{ asset('vendor/page-flip/page-flip.browser.js') }}"></script>
<script src="{{ asset('frontend/js/ssc-flipbook.js') }}"></script>
@endpush
