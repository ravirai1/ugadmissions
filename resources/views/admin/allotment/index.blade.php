@extends('layouts.admin')
@section('title', 'Seat Allotment')
@section('content')
@php $ist = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->timezone('Asia/Kolkata')->format('d M Y, h:i A') : ''; @endphp
<div class="card"><div class="card-body">
    <p class="small text-muted mb-2">The next round fills each course's vacant seats: General (UR) first by overall merit, then each reserved category. A candidate in several merit lists gets the seat in their highest DU preference where their merit is enough, and is shown as “Already selected – first preference” in the others. Candidates holding an offer can move up to a course they ranked higher (the old seat is released; an accepted seat stays confirmed). Candidates who declined an offer or let one lapse are not considered again.</p>
    @if ($notLocked->isNotEmpty())<p class="small mb-0">Not in this round (marks not finalised): {{ $notLocked->implode(', ') }}.</p>@endif
</div></div>

<div class="card"><div class="card-body">
    <h5 class="card-title">Next round (preview, nothing saved yet)</h5>
    @forelse ($preview['courses'] as $course)
        @php $list = $byCourse[$course->id] ?? collect(); @endphp
        <h6 class="mt-3">{{ $course->name }} <span class="small text-muted">{{ $list->count() }} to be offered · vacant after this round: {{ collect($preview['vacant'][$course->id] ?? [])->map(fn ($n, $c) => "$c $n")->implode(', ') ?: 'none' }}</span></h6>
        @if ($list->isNotEmpty())
            <table class="table table-sm table-compact">
                <thead><tr><th class="text-end">Rank</th><th>Application</th><th>Name</th><th>Category</th><th>Seat</th><th class="text-end">DU pref.</th><th class="text-end">CUET (85)</th><th class="text-end">Interview (15)</th><th class="text-end">Total (100)</th></tr></thead>
                @foreach ($list->sortBy(fn ($a) => $a['row']['rank']) as $a)
                    @php $r = $a['row']; @endphp
                    <tr><td class="text-end">{{ $r['rank'] }}</td><td>{{ $r['app']->application_no }}</td><td>{{ $r['app']->user->name }}</td><td>{{ $r['category'] }}</td><td><strong>{{ $a['bucket'] }}</strong>@if ($a['from'])<br><span class="badge bg-info">Moving up from {{ $a['from']->course->name }}</span>@endif</td><td class="text-end">{{ $r['choice']->du_preference }}</td><td class="text-end">{{ number_format($r['cuet'], 2) }}</td><td class="text-end">{{ number_format($r['interview'], 2) }}</td><td class="text-end fw-bold">{{ number_format($r['total'], 2) }}</td></tr>
                @endforeach
            </table>
        @endif
    @empty
        <p class="text-muted">No course has finalised marks yet.</p>
    @endforelse

    @if ($preview['assigned'])
        <form method="POST" action="{{ route('admin.allotment.publish') }}" class="row g-2 align-items-end mt-3 border-top pt-3" data-confirm="Publish this round? {{ count($preview['assigned']) }} candidates will be emailed an offer. This cannot be undone.">
            @csrf
            <div class="col-md-4"><label class="form-label small" for="deadline">Accept by (IST)</label><input type="datetime-local" class="form-control form-control-sm" id="deadline" name="deadline" value="{{ old('deadline', $defaultDeadline->format('Y-m-d\TH:i')) }}" required></div>
            <div class="col-md-4"><button class="btn btn-success btn-sm" type="submit"><i class="fas fa-paper-plane me-1"></i>Publish round and email {{ count($preview['assigned']) }} offers</button></div>
        </form>
    @endif
</div></div>

<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="card-title mb-0">Published rounds</h5>
        <a href="{{ route('admin.allotment.selections') }}" class="small">Selected list</a>
    </div>
    <table class="table table-sm stat-table mb-0">
        <thead><tr><th>Round</th><th class="text-end">Offers</th><th>Published</th><th>Deadline</th></tr></thead>
        @forelse ($rounds as $r)
            <tr><td>{{ $r->round }}</td><td class="text-end">{{ $r->n }}</td><td>{{ $ist($r->published) }}</td><td>{{ $ist($r->deadline) }}</td></tr>
        @empty
            <tr><td colspan="4" class="text-muted">No rounds yet.</td></tr>
        @endforelse
    </table>
</div></div>
@endsection
