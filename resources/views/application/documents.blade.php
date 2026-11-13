@extends('application._layout')
@section('step')
<div class="form-section">
    <h5>Upload documents</h5>
    <p class="small text-muted">Photograph and signature: JPG/PNG only. Other documents: JPG/PNG or PDF. Choose a file and it uploads immediately; uploading again replaces the earlier file.</p>
    @error('file')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Document</th><th>Format / max size</th><th>Uploaded</th><th style="width:30%">Upload</th></tr></thead>
            <tbody>
            @foreach ($required as $type)
                @php($doc = $uploaded[$type] ?? null)
                <tr>
                    <td>{{ \App\Support\DocumentTypes::label($type) }} <span class="req text-primary">*</span></td>
                    <td class="small">{{ strtoupper(implode(', ', array_unique(array_map(fn ($e) => $e === 'jpeg' ? 'jpg' : $e, \App\Support\DocumentTypes::exts($type))))) }}, {{ \App\Support\DocumentTypes::maxKb($type) >= 1024 ? (\App\Support\DocumentTypes::maxKb($type) / 1024).' MB' : \App\Support\DocumentTypes::maxKb($type).' KB' }}</td>
                    <td>
                        @if ($doc)
                            @if ($doc->isImage())
                                <a href="{{ route('application.document', $doc) }}" target="_blank"><img src="{{ route('application.document', $doc) }}" alt="" class="doc-thumb"></a>
                            @else
                                <a href="{{ route('application.document', $doc) }}" target="_blank"><i class="fa fa-file-pdf text-danger me-1"></i>View PDF</a>
                            @endif
                        @else
                            <span class="text-muted small">Not uploaded</span>
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('application.upload', $type) }}" enctype="multipart/form-data">
                            @csrf
                            <input type="file" class="form-control form-control-sm" name="file" accept="{{ collect(\App\Support\DocumentTypes::exts($type))->map(fn ($e) => '.'.$e)->implode(',') }}" data-autosubmit>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<form method="POST" action="{{ route('application.save', $step) }}" novalidate>
    @csrf
    @include('application._buttons')
</form>
@endsection
