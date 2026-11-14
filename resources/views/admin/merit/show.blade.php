@extends('layouts.admin')
@section('title', 'Merit List: '.$course->name)
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <a href="{{ route('admin.merit.index') }}" class="btn btn-outline-secondary btn-sm">All courses</a>
    <a href="{{ route('admin.merit.show', $course) }}" class="btn btn-sm {{ $category ? 'btn-outline-primary' : 'btn-primary' }}">Overall</a>
    @foreach ($categories as $cat)
        <a href="{{ route('admin.merit.show', [$course, 'category' => $cat]) }}" class="btn btn-sm {{ $category === $cat ? 'btn-primary' : 'btn-outline-primary' }}">{{ $cat }}@isset($seats[$cat]) ({{ $seats[$cat] }} seats)@endisset</a>
    @endforeach
    <a href="{{ route('admin.merit.export', $course) }}" class="btn btn-success btn-sm ms-auto"><i class="fas fa-file-excel me-1"></i>Excel</a>
</div>
@if (! $locked)<div class="alert alert-info py-2">Marks for this course are not finalised yet, so this list may still change and the course is not in seat allotment. <a href="{{ route('admin.interviews.course', $course) }}">Finalise marks</a></div>@endif
@if (! $seats)<div class="alert alert-warning py-2">No seats are set for this course. Add them under Master Data → Courses.</div>@endif

<div class="card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-sm table-compact align-middle">
            <thead><tr><th class="text-end">Rank</th>@if ($category)<th class="text-end">{{ $category }} rank</th>@endif<th>Application</th><th>Name</th><th>Category</th><th class="text-end">DU pref.</th><th class="text-end">CUET (85)</th><th class="text-end">Interview (15)</th><th class="text-end">Total (100)</th><th class="text-end">Class 12 %</th><th>Status</th></tr></thead>
            @forelse ($rows as $i => $r)
                @php $s = $status[$r['app']->id] ?? null; @endphp
                <tr class="{{ ($s['kind'] ?? '') === 'elsewhere' ? 'text-muted' : '' }}">
                    <td class="text-end">{{ $r['rank'] }}</td>
                    @if ($category)<td class="text-end">{{ $i + 1 }}</td>@endif
                    <td>{{ $r['app']->application_no }}</td>
                    <td>{{ $r['app']->user->name }}</td>
                    <td>{{ $r['category'] }}@if ($r['pwbd']) <span class="badge bg-info">PwBD</span>@endif</td>
                    <td class="text-end">{{ $r['choice']->du_preference }}</td>
                    <td class="text-end" title="{{ $r['cuet_raw'] }} / {{ $r['cuet_max'] }}">{{ number_format($r['cuet'], 2) }}</td>
                    <td class="text-end" title="{{ $r['panel'] }} panel members">{{ number_format($r['interview'], 2) }}</td>
                    <td class="text-end fw-bold">{{ number_format($r['total'], 2) }}</td>
                    <td class="text-end">{{ number_format($r['class12'], 2) }}</td>
                    <td class="small">
                        @if ($s)
                            @php $cls = ['accepted' => 'bg-success', 'offered' => 'bg-primary', 'next' => 'bg-info', 'elsewhere' => 'bg-secondary', 'declined' => 'bg-danger', 'lapsed' => 'bg-warning text-dark', 'upgraded' => 'bg-secondary', 'waiting' => 'bg-light text-dark'][$s['kind']] ?? 'bg-light text-dark'; @endphp
                            <span class="badge {{ $cls }}">{{ $s['label'] }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-muted">Nobody ranked yet. Candidates appear once they are marked present and at least one panel member has entered marks.</td></tr>
            @endforelse
        </table>
    </div>
    @if ($list['pending']->isNotEmpty() || $list['absent']->isNotEmpty() || $list['notMarked']->isNotEmpty())
        <p class="small text-muted mb-0">Not ranked: {{ $list['pending']->count() }} present without marks, {{ $list['absent']->count() }} absent, {{ $list['notMarked']->count() }} attendance not marked.</p>
    @endif
</div></div>
@endsection
