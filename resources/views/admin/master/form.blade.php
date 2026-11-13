@extends('layouts.admin')
@section('title', ($row->exists ? 'Edit' : 'Add').' – '.$def['label'])
@section('content')
<div class="card" style="max-width: 900px">
    <div class="card-body">
        <form method="POST" action="{{ $row->exists ? route('admin.master.update', [$def['type'], $row->id]) : route('admin.master.store', $def['type']) }}">
            @csrf
            @if ($row->exists) @method('PUT') @endif
            <div class="row g-3">
                @foreach ($def['fields'] as $name => $f)
                    @php
                        $val = old($name, $row->{$name});
                        if ($f['type'] === 'json' && is_array($val)) { $val = json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
                    @endphp
                    <div class="{{ $f['type'] === 'json' ? 'col-12' : 'col-md-6' }}">
                        @if ($f['type'] === 'bool')
                            <div class="form-check mt-4">
                                <input type="hidden" name="{{ $name }}" value="0">
                                <input class="form-check-input" type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1" @checked($val)>
                                <label class="form-check-label" for="{{ $name }}">{{ $f['label'] }}</label>
                            </div>
                        @else
                            <label class="form-label" for="{{ $name }}">{{ $f['label'] }}</label>
                            @if ($f['type'] === 'select')
                                <select class="form-select @error($name) is-invalid @enderror" id="{{ $name }}" name="{{ $name }}">
                                    <option value="">–</option>
                                    @foreach ($options[$name] as $k => $v)<option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $v }}</option>@endforeach
                                </select>
                            @elseif ($f['type'] === 'json')
                                <textarea class="form-control font-monospace small @error($name) is-invalid @enderror" id="{{ $name }}" name="{{ $name }}" rows="{{ $f['rows'] ?? 8 }}" spellcheck="false">{{ $val }}</textarea>
                            @else
                                <input type="{{ $f['type'] === 'number' ? 'number' : 'text' }}" class="form-control @error($name) is-invalid @enderror" id="{{ $name }}" name="{{ $name }}" value="{{ $val }}">
                            @endif
                            @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @endif
                        @if (! empty($f['help']))<div class="form-text">{{ $f['help'] }}</div>@endif
                    </div>
                @endforeach
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save</button>
                <a href="{{ route('admin.master.index', $def['type']) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
