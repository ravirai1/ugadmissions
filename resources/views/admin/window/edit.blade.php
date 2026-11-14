@extends('layouts.admin')
@section('title', 'Admission Window')
@section('content')
@php $local = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->timezone('Asia/Kolkata')->format('Y-m-d\TH:i') : ''; @endphp
<div class="alert {{ $mainOpen ? 'alert-success' : ($reopenActive ? 'alert-warning' : 'alert-secondary') }}"><strong>Now:</strong> {{ $status }}</div>
<form method="POST" action="{{ route('admin.window.update') }}">
    @csrf @method('PUT')
    <div class="card" style="max-width: 900px"><div class="card-body">
        <h5 class="card-title">Main round</h5>
        <div class="form-check form-switch mb-2">
            <input type="hidden" name="main_open" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="main_open" name="main_open" value="1" @checked(old('main_open', $s['main_open']))>
            <label class="form-check-label" for="main_open">Applications open</label>
        </div>
        <div style="max-width: 320px">
            <label class="form-label small" for="main_closes_at">Closes automatically at (IST, optional)</label>
            <input type="datetime-local" class="form-control form-control-sm" id="main_closes_at" name="main_closes_at" value="{{ old('main_closes_at', $local($s['main_closes_at'])) }}">
        </div>
        <p class="small text-muted mt-2 mb-0">When the main round is closed, applicants cannot fill, submit or pay, and cannot add courses, unless a course is reopened below. Submitted applications, scrutiny and payments already made are not affected.</p>
    </div></div>

    <div class="card" style="max-width: 900px"><div class="card-body">
        <h5 class="card-title">Reopen for vacant seats</h5>
        <p class="small text-muted">Works only while the main round is closed. Only the courses ticked here are shown and can be paid for. Anyone can apply for them: new applicants, and earlier applicants adding a course they had not applied for. No other course can be chosen or paid for.</p>
        <div class="form-check form-switch mb-2">
            <input type="hidden" name="reopen" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="reopen" name="reopen" value="1" @checked(old('reopen', $s['reopen']))>
            <label class="form-check-label" for="reopen">Reopen applications for the courses ticked below</label>
        </div>
        <div style="max-width: 320px" class="mb-3">
            <label class="form-label small" for="reopen_until">Reopened round ends at (IST, optional)</label>
            <input type="datetime-local" class="form-control form-control-sm @error('reopen_until') is-invalid @enderror" id="reopen_until" name="reopen_until" value="{{ old('reopen_until', $local($s['reopen_until'])) }}">
            @error('reopen_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @error('course_ids')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror
        <table class="table table-sm table-compact small">
            <thead><tr><th></th><th>Course</th><th class="text-end">Seats</th><th class="text-end">Offers held</th><th class="text-end">Vacant</th></tr></thead>
            @foreach ($courses as $c)
                <tr class="{{ $c['vacant'] > 0 && $c['held'] > 0 ? 'table-warning' : '' }}">
                    <td><input class="form-check-input" type="checkbox" name="course_ids[]" value="{{ $c['id'] }}" id="c{{ $c['id'] }}" @checked(in_array($c['id'], old('course_ids', $s['course_ids'])))></td>
                    <td><label for="c{{ $c['id'] }}">{{ $c['name'] }}</label></td>
                    <td class="text-end">{{ $c['seats'] ?: '–' }}</td>
                    <td class="text-end">{{ $c['held'] }}</td>
                    <td class="text-end fw-bold">{{ $c['seats'] ? $c['vacant'] : '–' }}</td>
                </tr>
            @endforeach
        </table>
        <p class="small text-muted mb-0">Seats exclude PwBD supernumerary seats. "Offers held" counts offers made or accepted.</p>
    </div></div>
    <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>Save</button>
</form>
@endsection
