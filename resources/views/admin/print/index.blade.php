@extends('layouts.admin')
@section('title', 'Print Desk')
@section('content')
@php $ist = fn ($d) => $d?->timezone('Asia/Kolkata')->format('d M Y, h:i A'); @endphp
<div class="row">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <h5 class="card-title">Find an application</h5>
            <form method="GET" class="d-flex gap-2 mb-3">
                <input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Application no., CUET no., email or mobile" required>
                <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search me-1"></i>Search</button>
            </form>
            @if (request()->filled('q'))
                @forelse ($results as $app)
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div><strong>{{ $app->application_no }}</strong> · {{ $app->user->name }}<br><span class="small text-muted">CUET {{ $app->user->cuet_no }} · {{ $app->fee_paid_at ? 'Fee paid' : 'Fee not paid' }}</span></div>
                            <div class="d-flex flex-wrap gap-1 align-items-start">
                                <a class="btn btn-sm btn-primary" href="{{ route('admin.print.form', $app) }}" target="_blank" rel="noopener"><i class="fas fa-print me-1"></i>Application form</a>
                                @foreach ($app->payments->filter->isSuccess() as $p)
                                    <a class="btn btn-sm btn-outline-success" href="{{ route('admin.payments.receipt', $p) }}" target="_blank" rel="noopener"><i class="fas fa-receipt me-1"></i>{{ $p->receipt_no }}</a>
                                @endforeach
                            </div>
                        </div>
                        @php $docs = $app->documents()->get(); @endphp
                        @if ($docs->isNotEmpty())
                            <div class="small mt-2">Documents:
                                @foreach ($docs as $d)
                                    <a href="{{ route('admin.applications.document', [$d, 'print' => 1]) }}" target="_blank" rel="noopener" class="me-2">{{ \App\Support\DocumentTypes::label($d->type) }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">No submitted application matches “{{ request('q') }}”.</p>
                @endforelse
            @endif
        </div></div>

        <div class="card"><div class="card-body">
            <h5 class="card-title">Bulk print application forms</h5>
            <p class="small text-muted">Prints every fee-paid form for the course, in application-number order, one form per page ({{ \App\Http\Controllers\Admin\PrintController::BULK_LIMIT }} at a time).</p>
            <form method="GET" action="{{ route('admin.print.bulk') }}" target="_blank" class="d-flex gap-2">
                <select class="form-select form-select-sm" name="course_id" required>
                    <option value="">Choose a course</option>
                    @foreach ($courses as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
                <button class="btn btn-primary btn-sm text-nowrap" type="submit"><i class="fas fa-print me-1"></i>Open for printing</button>
            </form>
            <p class="small text-muted mt-3 mb-0">Interview call letters will be printable here once they are generated.</p>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="card-title mb-0">Recent prints</h5>
                <a href="{{ route('admin.print.log') }}" class="small">Full print log</a>
            </div>
            <table class="table table-sm stat-table mb-0">
                @forelse ($recent as $l)
                    <tr><td>{{ \App\Models\PrintLog::ITEMS[$l->item] ?? $l->item }}<br><span class="small text-muted">{{ $l->application?->application_no ?? ($l->meta['course'] ?? '') }}</span></td><td class="small text-end">{{ $l->admin?->name }}<br>{{ $ist($l->created_at) }}</td></tr>
                @empty
                    <tr><td class="text-muted">Nothing printed yet.</td></tr>
                @endforelse
            </table>
        </div></div>
    </div>
</div>
@endsection
