@extends('layouts.admin')
@section('title', $notice->exists ? 'Edit Notice' : 'New Notice')
@section('content')
<div class="card" style="max-width: 820px"><div class="card-body">
    <form method="POST" action="{{ $notice->exists ? route('admin.notices.update', $notice) : route('admin.notices.store') }}">
        @csrf
        @if ($notice->exists) @method('PUT') @endif
        <div class="mb-3">
            <label class="form-label" for="title">Title</label>
            <input class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $notice->title) }}" maxlength="200" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="body">Notice</label>
            <textarea class="form-control @error('body') is-invalid @enderror" id="body" name="body" rows="8" maxlength="5000" required>{{ old('body', $notice->body) }}</textarea>
            <div class="form-text">Plain text. Leave a blank line between paragraphs.</div>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="audience">Who sees it</label>
            <select class="form-select" id="audience" name="audience">
                @foreach (\App\Models\Notice::AUDIENCES as $k => $label)<option value="{{ $k }}" @selected(old('audience', $notice->audience) === $k)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" @checked(old('is_published', $notice->is_published))>
            <label class="form-check-label" for="is_published">Published</label>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="is_pinned" name="is_pinned" value="1" @checked(old('is_pinned', $notice->is_pinned))>
            <label class="form-check-label" for="is_pinned">Pin to the top</label>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="in_ticker" name="in_ticker" value="1" @checked(old('in_ticker', $notice->in_ticker))>
            <label class="form-check-label" for="in_ticker">Show in the scrolling notice bar on the website</label>
            <div class="form-text">Only for notices that everyone can see. The bar shows at the top of every public page.</div>
        </div>
        <button class="btn btn-primary" type="submit">Save</button>
        <a href="{{ route('admin.notices.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
@endsection
