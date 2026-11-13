@extends('layouts.frontend')
@section('title', 'Notices')
@section('page_title', 'Notices')
@section('content')
<div class="container" style="max-width: 900px">
    @forelse ($notices as $n)
        <div class="p-4 border mb-3" id="notice-{{ $n->id }}">
            <h5 class="mb-1">@if ($n->is_pinned)<i class="fa fa-thumbtack text-primary me-2"></i>@endif{{ $n->title }}</h5>
            <p class="small text-muted mb-3">{{ $n->published_at?->timezone('Asia/Kolkata')->format('d M Y') }}</p>
            <div style="white-space: pre-line">{{ $n->body }}</div>
        </div>
    @empty
        <p class="text-muted">No notices yet. Please check again later.</p>
    @endforelse
    {{ $notices->links() }}
</div>
@endsection
