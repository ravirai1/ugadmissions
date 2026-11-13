@extends('layouts.admin')
@section('title', 'Notices')
@section('content')
@php $ist = fn ($d) => $d?->timezone('Asia/Kolkata')->format('d M Y, h:i A'); @endphp
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="small text-muted mb-0">Published notices appear on the home page (audience “Everyone”) and on applicants’ dashboards. You can also email a notice once to its audience.</p>
        <a href="{{ route('admin.notices.create') }}" class="btn btn-primary btn-sm text-nowrap"><i class="fas fa-plus me-1"></i>New notice</a>
    </div>
    @error('email')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Title</th><th>Audience</th><th>Status</th><th>Email</th><th></th></tr></thead>
            <tbody>
            @forelse ($notices as $n)
                <tr>
                    <td>@if ($n->is_pinned)<i class="fas fa-thumbtack text-warning me-1" title="Pinned"></i>@endif<strong>{{ $n->title }}</strong><br><span class="small text-muted">{{ $n->author?->name }} · {{ $ist($n->created_at) }}</span></td>
                    <td class="small">{{ \App\Models\Notice::AUDIENCES[$n->audience] ?? $n->audience }}</td>
                    <td>@if ($n->is_published)<span class="badge bg-success">Published</span><br><span class="small text-muted">{{ $ist($n->published_at) }}</span>@else<span class="badge bg-secondary">Draft</span>@endif</td>
                    <td class="small">
                        @if ($n->emailed_at)
                            Sent to {{ number_format($n->emailed_count) }}<br><span class="text-muted">{{ $ist($n->emailed_at) }}</span>
                        @elseif ($n->is_published)
                            <form method="POST" action="{{ route('admin.notices.email', $n) }}" data-confirm="Email this notice to {{ \App\Models\Notice::AUDIENCES[$n->audience] ?? '' }}? This cannot be undone.">@csrf<button class="btn btn-outline-primary btn-sm" type="submit"><i class="fas fa-envelope me-1"></i>Email</button></form>
                        @else
                            <span class="text-muted">Publish first</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.notices.edit', $n) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
                        <form method="POST" action="{{ route('admin.notices.destroy', $n) }}" class="d-inline" data-confirm="Delete this notice?">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm" type="submit">Delete</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">No notices yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $notices->links() }}
</div></div>
@endsection
