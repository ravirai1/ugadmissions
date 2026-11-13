@extends('layouts.admin')
@section('title', 'Helpdesk Report')
@section('content')
<div class="row">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <h5 class="card-title">Last 14 days</h5>
            <table class="table table-sm stat-table">
                <thead><tr><th>Date</th><th class="text-end">Chats</th><th class="text-end">Queries</th><th class="text-end">Avg. first reply (min)</th></tr></thead>
                @foreach (array_reverse($days) as $d)
                    <tr><td>{{ \Illuminate\Support\Carbon::parse($d['date'])->format('d M') }}</td><td class="text-end">{{ $d['chat'] }}</td><td class="text-end">{{ $d['query'] }}</td><td class="text-end">{{ $d['avg'] ?? '–' }}</td></tr>
                @endforeach
            </table>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h5 class="card-title">Replies by staff</h5>
            <table class="table table-sm stat-table">
                @forelse ($byStaff as $r)<tr><td>{{ $r->name }}</td><td class="text-end">{{ $r->n }}</td></tr>@empty<tr><td class="text-muted">No replies yet.</td></tr>@endforelse
            </table>
        </div></div>
    </div>
</div>
<a href="{{ route('admin.helpdesk.index') }}" class="btn btn-outline-secondary btn-sm">Back to inbox</a>
@endsection
