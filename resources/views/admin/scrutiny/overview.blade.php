@extends('layouts.admin')
@section('title', 'Scrutiny Overview')
@section('content')
@php
    $ist = fn ($d) => $d?->timezone('Asia/Kolkata')->format('d M Y, h:i A');
    $S = \App\Models\Scrutiny::class;
@endphp
<div class="row">
    <div class="col-md-4"><div class="card card-stats"><div class="card-body"><p class="card-category mb-1">Applications received (fee paid)</p><h3 class="card-title mb-0">{{ $received }}</h3><span class="small text-muted">{{ $christian }} in the Christian category</span></div></div></div>
    @foreach (['academic' => 'Academic scrutiny', 'religious' => 'Religious scrutiny'] as $t => $title)
        @php $st = $streams[$t]; @endphp
        <div class="col-md-4"><div class="card"><div class="card-body">
            <a href="{{ route('admin.scrutiny.index', [$t, 'status' => 'all']) }}" class="fw-bold">{{ $title }}</a>
            <div class="small mt-1">Total <strong>{{ $st['total'] }}</strong> · Completed <strong class="text-success">{{ $st['completed'] }}</strong> · Pending <strong class="text-warning">{{ $st['pending'] }}</strong> · For review <strong class="text-primary">{{ $st['review'] }}</strong></div>
            <div class="progress mt-2" style="height: 8px" role="img" aria-label="{{ $st['completed'] }} of {{ $st['total'] }} completed">
                <div class="progress-bar bg-success" style="width: {{ $st['total'] ? round($st['completed'] / $st['total'] * 100) : 0 }}%"></div>
                <div class="progress-bar bg-primary" style="width: {{ $st['total'] ? round($st['review'] / $st['total'] * 100) : 0 }}%"></div>
            </div>
            <div class="small text-muted mt-1">Approved {{ $st['counts'][$S::APPROVED] ?? 0 }} · Rejected {{ $st['counts'][$S::REJECTED] ?? 0 }} · Query raised {{ $st['counts'][$S::QUERY] ?? 0 }} · Verification started {{ $st['started'] }}</div>
        </div></div></div>
    @endforeach
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">Marked for review ({{ $review->count() }})</h5>
        @if ($review->isEmpty())
            <p class="text-muted small mb-0">Nothing waiting for review.</p>
        @else
            <div class="table-responsive"><table class="table table-sm table-compact small align-middle">
                <thead><tr><th>Application</th><th>Scrutiny</th><th>Comment</th><th>Marked by</th><th></th></tr></thead>
                @foreach ($review as $r)
                    <tr>
                        <td>{{ $r->application->application_no }}<br>{{ $r->application->user->name }}</td>
                        <td>{{ $r->type === 'academic' ? 'Academic – '.$r->applicationCourse?->course->name : 'Religious' }}</td>
                        <td style="max-width: 420px">{{ $r->review_comment }}</td>
                        <td>{{ $r->reviewFlaggedBy?->name }}<br><span class="text-muted">{{ $ist($r->review_at) }}</span></td>
                        <td><a href="{{ route('admin.scrutiny.show', $r) }}" class="btn btn-primary btn-sm">Decide</a></td>
                    </tr>
                @endforeach
            </table></div>
        @endif
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <h5 class="card-title">Academic scrutiny by course</h5>
            <table class="table table-sm table-compact small">
                <thead><tr><th>Course</th><th class="text-end">Total</th><th class="text-end">Done</th><th class="text-end">Pending</th><th class="text-end">Review</th><th class="text-end">Rejected</th></tr></thead>
                @forelse ($courseRows as $course => $c)
                    <tr>
                        <td>{{ $course }}</td>
                        <td class="text-end">{{ $c->sum() }}</td>
                        <td class="text-end">{{ ($c[$S::APPROVED] ?? 0) + ($c[$S::REJECTED] ?? 0) }}</td>
                        <td class="text-end">{{ ($c[$S::PENDING] ?? 0) + ($c[$S::QUERY] ?? 0) }}</td>
                        <td class="text-end">{{ $c[$S::REVIEW] ?? 0 }}</td>
                        <td class="text-end">{{ $c[$S::REJECTED] ?? 0 }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No academic scrutiny yet.</td></tr>
                @endforelse
            </table>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <h5 class="card-title">Rejected, with final comment</h5>
            @forelse ($rejected as $r)
                <div class="border-bottom py-2 small">
                    <a href="{{ route('admin.scrutiny.show', $r) }}"><strong>{{ $r->application->application_no }}</strong></a> {{ $r->application->user->name }} ·
                    {{ $r->type === 'academic' ? 'Academic – '.$r->applicationCourse?->course->name : 'Religious (treated as General)' }}
                    <div>{{ $r->remarks }}</div>
                    <span class="text-muted">{{ $r->reviewer?->name }}, {{ $ist($r->reviewed_at) }}</span>
                </div>
            @empty
                <p class="text-muted small mb-0">No rejections.</p>
            @endforelse
        </div></div>
    </div>
</div>
@endsection
