@extends('layouts.admin')
@section('title', 'Merit Lists')
@section('content')
<div class="row">
    <div class="col-xl-7">
        <div class="card"><div class="card-body">
            <h5 class="card-title">Course-wise merit lists</h5>
            <p class="small text-muted">Total 100 = CUET (85) from the verified CUET score + interview (15), the average of the panel members' marks. Ties go to the higher CUET part, then higher Class 12 %, then the older applicant.</p>
            <table class="table table-sm stat-table">
                <thead><tr><th>Course</th><th class="text-end">Ranked</th><th class="text-end">Marks pending</th><th class="text-end">Absent</th><th class="text-end">Top total (100)</th><th>Marks</th></tr></thead>
                @foreach ($courses as $c)
                    @php $l = $lists[$c->id]; @endphp
                    <tr>
                        <td><a href="{{ route('admin.merit.show', $c) }}">{{ $c->name }}</a></td>
                        <td class="text-end">{{ $l['ranked']->count() }}</td>
                        <td class="text-end">{{ $l['pending']->count() }}</td>
                        <td class="text-end">{{ $l['absent']->count() }}</td>
                        <td class="text-end">{{ $l['ranked']->first()['total'] ?? '–' }}</td>
                        <td>@if (in_array($c->id, $locked, true))<span class="badge bg-success">Finalised</span>@else<span class="badge bg-secondary">Open</span>@endif</td>
                    </tr>
                @endforeach
            </table>
        </div></div>
    </div>
    <div class="col-xl-5">
        <div class="card"><div class="card-body">
            <h5 class="card-title">Highest merit (all courses)</h5>
            <table class="table table-sm stat-table">
                <thead><tr><th>#</th><th>Candidate</th><th class="text-end">CUET (85)</th><th class="text-end">Int. (15)</th><th class="text-end">Total (100)</th></tr></thead>
                @forelse ($highest as $i => $r)
                    <tr><td>{{ $i + 1 }}</td><td>{{ $r['app']->user->name }}<br><span class="small text-muted">{{ $r['app']->application_no }} · {{ $r['course']->name }} · {{ $r['category'] }}</span></td><td class="text-end">{{ number_format($r['cuet'], 2) }}</td><td class="text-end">{{ number_format($r['interview'], 2) }}</td><td class="text-end fw-bold">{{ number_format($r['total'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No interview marks yet.</td></tr>
                @endforelse
            </table>
        </div></div>
    </div>
</div>
@endsection
