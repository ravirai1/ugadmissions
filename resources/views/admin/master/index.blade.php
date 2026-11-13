@extends('layouts.admin')
@section('title', 'Master Data – '.$def['label'])
@section('content')
@php
    $cell = function ($row, $col) use ($def, $options) {
        $f = $def['fields'][$col] ?? ['type' => 'text'];
        $v = $row->{$col};
        return match ($f['type']) {
            'bool' => $v ? 'Yes' : 'No',
            'select' => $options[$col][$v] ?? ($v ?? '–'),
            default => $v === null || $v === '' ? '–' : $v,
        };
    };
@endphp
<ul class="nav nav-pills mb-3 flex-wrap">
    @foreach ($types as $key => $t)
        <li class="nav-item"><a class="nav-link {{ $key === $def['type'] ? 'active' : '' }}" href="{{ route('admin.master.index', $key) }}">{{ $t['label'] }}</a></li>
    @endforeach
    <li class="nav-item"><a class="nav-link" href="{{ route('admin.settings.fees') }}">Fees</a></li>
</ul>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
            <form class="d-flex flex-wrap gap-2" method="GET">
                <input class="form-control form-control-sm" style="width:220px" name="q" value="{{ request('q') }}" placeholder="Search">
                @if ($f = $def['filter'] ?? null)
                    <select class="form-select form-select-sm" style="width:220px" name="{{ $f }}">
                        <option value="">All {{ strtolower($def['fields'][$f]['label']) }}</option>
                        @foreach ($options[$f] as $k => $v)<option value="{{ $k }}" @selected((string) request($f) === (string) $k)>{{ $v }}</option>@endforeach
                    </select>
                @endif
                <button class="btn btn-primary btn-sm">Filter</button>
            </form>
            <a href="{{ route('admin.master.create', $def['type']) }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>Add</a>
        </div>

        @if ($p = $def['bulk'] ?? null)
            <details class="mb-3">
                <summary class="small fw-bold">Add many at once</summary>
                <form method="POST" action="{{ route('admin.master.bulk', $def['type']) }}" class="row g-2 mt-1">
                    @csrf
                    <div class="col-md-4">
                        <select class="form-select form-select-sm" name="{{ $p }}" required>
                            <option value="">{{ $def['fields'][$p]['label'] }}</option>
                            @foreach ($options[$p] as $k => $v)<option value="{{ $k }}" @selected((string) request($p) === (string) $k)>{{ $v }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6"><textarea class="form-control form-control-sm" name="names" rows="4" placeholder="One name per line" required></textarea></div>
                    <div class="col-md-2"><button class="btn btn-outline-success btn-sm w-100" type="submit">Add all</button></div>
                </form>
            </details>
        @endif

        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
                <thead><tr>@foreach ($def['columns'] as $col)<th>{{ $def['fields'][$col]['label'] }}</th>@endforeach<th></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr class="{{ $row->is_active ? '' : 'text-muted' }}">
                        @foreach ($def['columns'] as $col)<td>{{ $cell($row, $col) }}</td>@endforeach
                        <td class="text-nowrap">
                            <a href="{{ route('admin.master.edit', [$def['type'], $row->id]) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                            <form method="POST" action="{{ route('admin.master.toggle', [$def['type'], $row->id]) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-outline-{{ $row->is_active ? 'danger' : 'success' }} btn-sm" type="submit">{{ $row->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($def['columns']) + 1 }}" class="text-center text-muted">Nothing here yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $rows->links('pagination::bootstrap-5') }}
        <p class="small text-muted mb-0">Records are deactivated instead of deleted, because submitted applications refer to them. Deactivated records are hidden from new applicants.</p>
    </div>
</div>
@endsection
