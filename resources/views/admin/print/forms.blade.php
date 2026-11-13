@php $ist = fn ($d) => $d?->timezone('Asia/Kolkata')->format('d M Y, h:i A'); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Application form {{ $title }} | St. Stephen's College</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link href="{{ asset('favicon.ico') }}" rel="icon">
    <link href="{{ asset('frontend/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        body { background: #f4f4f4; font-size: 13px; }
        .sheet { max-width: 860px; margin: 20px auto; background: #fff; padding: 28px 32px; border: 1px solid #ddd; }
        .sheet + .sheet { page-break-before: always; break-before: page; }
        .form-section { border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 14px; margin-bottom: 10px; break-inside: avoid; }
        .form-section h5 { font-size: 14px; font-weight: 700; border-bottom: 1px solid #eee; padding-bottom: 4px; margin-bottom: 8px; }
        .preview-table th { width: 32%; background: #fafafa; font-weight: 600; }
        .table { margin-bottom: 0; }
        .table td, .table th { padding: 3px 6px; }
        .doc-thumb { width: 100px; height: 120px; object-fit: cover; border: 1px solid #ccc; }
        .print-foot { border-top: 1px solid #ddd; margin-top: 12px; padding-top: 6px; font-size: 11px; color: #666; }
        .toolbar { max-width: 860px; margin: 16px auto 0; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .sheet { border: 0; margin: 0; max-width: none; padding: 0; }
            a { color: inherit; text-decoration: none; }
            @page { size: A4; margin: 12mm; }
        }
    </style>
</head>
<body>
<div class="toolbar no-print d-flex flex-wrap align-items-center gap-2">
    <button type="button" class="btn btn-primary btn-sm" data-print>Print / Save as PDF</button>
    <a href="{{ route('admin.print.index') }}" class="btn btn-outline-secondary btn-sm">Back to print desk</a>
    @isset($total)
        <span class="small text-muted ms-2">{{ $title }}: forms {{ $apps->isEmpty() ? 0 : $offset + 1 }}–{{ $offset + $apps->count() }} of {{ $total }}</span>
        @if ($next)<a href="{{ $next }}" class="btn btn-outline-primary btn-sm ms-auto">Next {{ \App\Http\Controllers\Admin\PrintController::BULK_LIMIT }}</a>@endif
    @endisset
</div>

@forelse ($apps as $app)
    @php
        $user = $app->user;
        $uploaded = $app->documents->keyBy('type');
        $required = $app->requiredDocumentTypes();
        $docUrl = fn ($d) => route('admin.applications.document', $d);
    @endphp
    <div class="sheet">
        <div class="d-flex align-items-center border-bottom pb-2 mb-3">
            <img src="{{ asset('images/logo.png') }}" alt="St. Stephen's College" style="height:56px">
            <div class="ms-3">
                <div class="fw-bold">St. Stephen's College, University of Delhi</div>
                <div class="small text-muted">UG Admissions 2027 · Application form</div>
            </div>
            <div class="ms-auto text-end">
                <div class="fw-bold fs-6">{{ $app->application_no }}</div>
                <div class="small">CSAS: {{ $app->csas_application_no }}</div>
                <div class="small">{{ $app->fee_paid_at ? 'Fee paid '.$app->fee_paid_at->timezone('Asia/Kolkata')->format('d M Y') : 'Fee not paid' }}</div>
            </div>
        </div>

        @include('application._details')

        @php $paid = $app->payments->filter->isSuccess(); @endphp
        @if ($paid->isNotEmpty())
            <div class="form-section">
                <h5>Fee receipts</h5>
                <table class="table table-sm table-bordered">
                    <tr><th>Receipt no.</th><th>Purpose</th><th class="text-end">Amount</th><th>Paid on</th></tr>
                    @foreach ($paid as $p)
                        <tr><td>{{ $p->receipt_no }}</td><td>{{ $p->purposeLabel() }}</td><td class="text-end">₹{{ number_format($p->amount) }}</td><td>{{ $ist($p->paid_at) }}</td></tr>
                    @endforeach
                </table>
            </div>
        @endif

        <div class="print-foot d-flex justify-content-between">
            <span>Submitted {{ $ist($app->submitted_at) }}</span>
            <span>Printed by {{ $printedBy }} on {{ $ist(now()) }}</span>
        </div>
    </div>
@empty
    <div class="sheet"><p class="mb-0 text-muted">No fee-paid applications for this course yet.</p></div>
@endforelse
<script src="{{ asset('frontend/js/ssc-pay.js') }}"></script>
</body>
</html>
