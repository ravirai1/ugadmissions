@extends('layouts.admin')
@section('title', 'Seat Matrix')
@section('content')
<div class="card"><div class="card-body">
    <p class="small text-muted mb-2">Enter the seats for each course and category. Leave a box empty for no seats. General (UR) seats are filled first by overall merit, then each category from its own candidates. To add a new category, add it under Master Data → Categories and it appears here.</p>
    <p class="small text-muted"><strong>PwBD seats are supernumerary</strong>: they are extra, on top of the course's seats, and go to PwBD candidates in merit order who did not get a regular seat. Leave the PwBD box empty to use the percentage below (rounded up), or type a number to fix it for that course (0 for none).</p>
    <form method="POST" action="{{ route('admin.seats.update') }}">
        @csrf @method('PUT')
        <div class="d-flex align-items-center gap-2 mb-3">
            <label class="form-label small mb-0" for="pwbd_percent">PwBD supernumerary seats (% of each course's seats)</label>
            <input type="number" class="form-control form-control-sm" style="width:90px" id="pwbd_percent" name="pwbd_percent" value="{{ old('pwbd_percent', $percent) }}" min="0" max="100" step="0.5" required>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-compact align-middle">
                <thead>
                    <tr>
                        <th>Course</th>
                        @foreach ($categories as $c)<th class="text-center" title="{{ $c->name }}">{{ $c->code }}</th>@endforeach
                        <th class="text-end">Seats</th>
                        <th class="text-center">PwBD (extra)</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($courses as $course)
                    @php $s = $course->seats ?? []; $intake = array_sum(array_map('intval', array_diff_key($s, ['PWBD' => 1]))); @endphp
                    <tr>
                        <td>{{ $course->name }}@unless ($course->is_active) <span class="badge bg-secondary">Inactive</span>@endunless @if ($offers[$course->id] ?? 0)<br><span class="small text-warning">Offers already made: changes apply to later rounds</span>@endif</td>
                        @foreach ($categories as $c)
                            <td><input type="number" class="form-control form-control-sm text-center" style="min-width:64px" name="seats[{{ $course->id }}][{{ $c->code }}]" value="{{ old("seats.{$course->id}.{$c->code}", $s[$c->code] ?? '') }}" min="0" max="9999" aria-label="{{ $course->name }} {{ $c->code }} seats"></td>
                        @endforeach
                        <td class="text-end fw-bold">{{ $intake }}</td>
                        <td><input type="number" class="form-control form-control-sm text-center" style="min-width:64px" name="seats[{{ $course->id }}][PWBD]" value="{{ old("seats.{$course->id}.PWBD", $s['PWBD'] ?? '') }}" min="0" max="9999" placeholder="{{ \App\Services\AllotmentService::pwbdSeats($course->replicate()->forceFill(['seats' => array_diff_key($s, ['PWBD' => 1])])) }}" aria-label="{{ $course->name }} PwBD extra seats"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="small text-muted">The grey number in an empty PwBD box is what the percentage gives.</p>
        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-save me-1"></i>Save seat matrix</button>
    </form>
</div></div>
@endsection
