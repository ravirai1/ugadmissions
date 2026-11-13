@extends('layouts.admin')
@section('title', 'Print Log')
@section('content')
@php $ist = fn ($d) => $d?->timezone('Asia/Kolkata')->format('d M Y, h:i A'); @endphp
<div class="card"><div class="card-body">
    <form class="row g-2 mb-3" method="GET">
        <div class="col-md-3"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Application no."></div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" name="item">
                <option value="">Everything</option>
                @foreach ($items as $k => $label)<option value="{{ $k }}" @selected(request('item') === $k)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" name="admin">
                <option value="">Any staff member</option>
                @foreach ($staff as $id => $name)<option value="{{ $id }}" @selected((string) request('admin') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3"><button class="btn btn-primary btn-sm" type="submit">Filter</button> <a href="{{ route('admin.print.log') }}" class="btn btn-outline-secondary btn-sm">Clear</a></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>When</th><th>Printed</th><th>Application</th><th>Details</th><th>By</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                <tr>
                    <td class="text-nowrap">{{ $ist($l->created_at) }}</td>
                    <td>{{ $items[$l->item] ?? $l->item }}</td>
                    <td>{{ $l->application?->application_no }}</td>
                    <td class="small">@foreach ($l->meta ?? [] as $k => $v){{ ucfirst($k) }}: {{ is_scalar($v) ? $v : json_encode($v) }}@if (! $loop->last) · @endif @endforeach</td>
                    <td>{{ $l->admin?->name }}</td>
                    <td class="small">{{ $l->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">Nothing printed yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</div></div>
@endsection
