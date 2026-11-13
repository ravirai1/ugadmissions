<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Interview call letter | St. Stephen's College</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link href="{{ asset('favicon.ico') }}" rel="icon">
    <link href="{{ asset('frontend/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        body { background: #f4f4f4; }
        .letter { max-width: 780px; margin: 20px auto; background: #fff; padding: 32px; border: 1px solid #ddd; }
        .letter + .letter { page-break-before: always; break-before: page; }
        .letter th { width: 34%; background: #fafafa; }
        .photo { width: 110px; height: 135px; object-fit: cover; border: 1px solid #ccc; }
        .qr { width: 130px; height: 130px; }
        .toolbar { max-width: 780px; margin: 16px auto 0; }
        @media print { body { background: #fff; } .no-print { display: none !important; } .letter { border: 0; margin: 0; max-width: none; padding: 0; } @page { size: A4; margin: 14mm; } }
    </style>
</head>
<body>
<div class="toolbar no-print d-flex gap-2">
    <button type="button" class="btn btn-primary btn-sm" data-print>Print / Save as PDF</button>
    <a href="{{ $back }}" class="btn btn-outline-secondary btn-sm">Back</a>
    @isset($title)<span class="small text-muted align-self-center ms-2">{{ $title }}: {{ $letters->count() }} {{ \Illuminate\Support\Str::plural('letter', $letters->count()) }}</span>@endisset
</div>
@forelse ($letters as $l)
    @php $app = $l->application; $photo = $app->documents->firstWhere('type', 'photo'); @endphp
    <div class="letter">
        <div class="d-flex align-items-center border-bottom pb-3 mb-3">
            <img src="{{ asset('images/logo.png') }}" alt="St. Stephen's College" style="height:60px">
            <div class="ms-3">
                <div class="fw-bold">St. Stephen's College, University of Delhi</div>
                <div class="small text-muted">UG Admissions 2027</div>
            </div>
            <div class="ms-auto text-end">
                <div class="fw-bold">Interview Call Letter</div>
                <div class="small">{{ $l->letter_no }}</div>
            </div>
        </div>

        <div class="d-flex gap-3 mb-3">
            <table class="table table-sm table-bordered mb-0 flex-grow-1">
                <tr><th>Name</th><td>{{ $app->user->name }}</td></tr>
                <tr><th>Application no.</th><td>{{ $app->application_no }}</td></tr>
                <tr><th>CUET application no.</th><td>{{ $app->user->cuet_no }}</td></tr>
                <tr><th>Course</th><td><strong>{{ $l->course->name }}</strong></td></tr>
                <tr><th>Interview date</th><td><strong>{{ $l->interview_date->format('l, d M Y') }}</strong></td></tr>
                <tr><th>Reporting time</th><td><strong>{{ $l->reporting_time }}</strong></td></tr>
                <tr><th>Venue</th><td>{{ $l->venue }}</td></tr>
            </table>
            <div class="text-center">
                @if ($photo)<img src="{{ $photoUrl($photo) }}" alt="Photo" class="photo d-block mb-2">@endif
                <img src="{{ \App\Services\InterviewService::qr($l) }}" alt="QR code for attendance" class="qr">
                <div class="small text-muted">Show at the attendance desk</div>
            </div>
        </div>

        <h6>Please note</h6>
        <ol class="small mb-4">
            <li>Report at the venue at the time given above. Late arrivals may not be interviewed.</li>
            <li>Bring this call letter (printed or on your phone), a government photo ID, and the originals of the documents you uploaded, with one set of self-attested photocopies.</li>
            <li>Christian-category applicants must also bring the original baptism certificate and the priest's letter.</li>
            <li>The merit list is made out of 100: 85 from your verified CUET (UG) 2027 score and 15 from the interview.</li>
            <li>Being called for an interview does not guarantee admission.</li>
        </ol>
        <div class="d-flex justify-content-between small text-muted border-top pt-2">
            <span>This is a computer-generated call letter and needs no signature.</span>
            <span>Admissions Office</span>
        </div>
    </div>
@empty
    <div class="letter"><p class="mb-0 text-muted">No call letters.</p></div>
@endforelse
<script src="{{ asset('frontend/js/ssc-pay.js') }}"></script>
</body>
</html>
