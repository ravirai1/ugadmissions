@extends('layouts.admin')
@section('title', 'Prospectus')
@section('content')
<div class="card" style="max-width: 820px"><div class="card-body">
    <p class="small text-muted">Upload the prospectus as a PDF (up to {{ (int) (\App\Support\Prospectus::MAX_KB / 1024) }} MB). It appears on the website as a book whose pages turn, with a download button. Uploading a new file replaces the old one.</p>
    @if ($current)
        <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong>{{ $current['title'] }}</strong><br>
                <span class="small text-muted">{{ $current['name'] ?? 'prospectus.pdf' }} · {{ number_format(($current['size'] ?? 0) / 1048576, 1) }} MB · uploaded {{ \Illuminate\Support\Carbon::parse($current['uploaded_at'] ?? now())->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</span>
            </div>
            <div class="text-nowrap">
                <a href="{{ route('prospectus') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fas fa-book-open me-1"></i>View on website</a>
                <form method="POST" action="{{ route('admin.prospectus.destroy') }}" class="d-inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash me-1"></i>Remove</button>
                </form>
            </div>
        </div>
    @endif
    <form method="POST" action="{{ route('admin.prospectus.update') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="title">Title shown on the website</label>
            <input class="form-control @error('title') is-invalid @enderror" id="title" name="title" maxlength="150" required value="{{ old('title', $current['title'] ?? 'Undergraduate Admissions Prospectus 2027–28') }}">
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="file">{{ $current ? 'Replace PDF (optional)' : 'Prospectus PDF' }}</label>
            <input class="form-control @error('file') is-invalid @enderror" type="file" id="file" name="file" accept="application/pdf,.pdf" @unless ($current) required @endunless>
            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-upload me-1"></i>Save</button>
    </form>
</div></div>
@endsection
