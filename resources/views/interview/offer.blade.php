@php $o = $offer; $app = $o->application; $ist = fn ($d) => $d?->timezone('Asia/Kolkata')->format('d M Y, h:i A'); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Offer letter {{ $o->offer_no }} | St. Stephen's College</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link href="{{ asset('favicon.ico') }}" rel="icon">
    <link href="{{ asset('frontend/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        body { background: #f4f4f4; }
        .letter { max-width: 780px; margin: 24px auto; background: #fff; padding: 32px; border: 1px solid #ddd; }
        .letter th { width: 38%; background: #fafafa; }
        @media print { body { background: #fff; } .no-print { display: none !important; } .letter { border: 0; margin: 0; max-width: none; } }
    </style>
</head>
<body>
<div class="letter">
    <div class="d-flex align-items-center border-bottom pb-3 mb-3">
        <img src="{{ asset('images/logo.png') }}" alt="St. Stephen's College" style="height:60px">
        <div class="ms-auto text-end">
            <div class="fw-bold">Offer of Admission</div>
            <div class="small text-muted">UG Admissions 2027 · Round {{ $o->round }}</div>
        </div>
    </div>

    @if (session('status'))<div class="alert alert-success no-print">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger no-print">{{ $errors->first() }}</div>@endif

    <p>Dear {{ $app->user->name }},</p>
    @if ($o->upgradedFrom)<div class="alert alert-info">Your seat has been moved up from {{ $o->upgradedFrom->course->name }} to a course you placed higher in your DU preferences.@if ($o->status === 'accepted') You had already accepted your earlier seat, so this seat is confirmed.@endif</div>@endif
    <p>We are pleased to offer you a seat in <strong>{{ $o->course->name }}</strong> at St. Stephen's College for the academic year 2027–28, subject to verification of your original documents and your admission through DU CSAS (UG) 2027.</p>

    <table class="table table-sm table-bordered">
        <tr><th>Offer no.</th><td>{{ $o->offer_no }}</td></tr>
        <tr><th>Application no.</th><td>{{ $app->application_no }}</td></tr>
        <tr><th>Course</th><td>{{ $o->course->name }}</td></tr>
        <tr><th>Seat category</th><td>{{ $o->seat_category }}</td></tr>
        <tr><th>CUET part (out of 85)</th><td>{{ $o->cuet_part }}</td></tr>
        <tr><th>Interview (out of 15)</th><td>{{ $o->interview_part }}</td></tr>
        <tr><th>Total (out of 100)</th><td><strong>{{ $o->total }}</strong></td></tr>
        <tr><th>Rank in course merit list</th><td>{{ $o->merit_rank }}</td></tr>
        <tr><th>Accept by</th><td><strong>{{ $ist($o->deadline_at) }} IST</strong></td></tr>
        <tr><th>Status</th><td><strong>{{ \App\Models\SeatOffer::STATUSES[$o->status] }}</strong>@if ($o->responded_at) on {{ $ist($o->responded_at) }}@endif</td></tr>
    </table>

    @if ($o->canAccept())
        <div class="border rounded p-3 no-print mb-3">
            <p class="mb-2">If you do not accept by the deadline, the seat lapses and is offered to the next candidate. Accepting keeps this seat for you. If a seat later opens in a course you placed higher in your DU preferences, you will be moved up automatically and this seat released.</p>
            <form method="POST" action="{{ route('applicant.offer.respond', $o) }}">
                @csrf
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm" required>
                    <label class="form-check-label" for="confirm">I have read the offer and confirm my decision.</label>
                </div>
                <button class="btn btn-success" type="submit" name="decision" value="accept">Accept seat</button>
                <button class="btn btn-outline-danger ms-2" type="submit" name="decision" value="decline">Decline</button>
            </form>
        </div>
    @endif

    <p class="small text-muted mb-0">This is a computer-generated letter and needs no signature. Generated {{ $ist(now()) }}.</p>

    <div class="no-print mt-4 d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm" data-print>Print / Save as PDF</button>
        <a href="{{ route('applicant.dashboard') }}" class="btn btn-outline-secondary btn-sm">Back to dashboard</a>
    </div>
</div>
<script src="{{ asset('frontend/js/ssc-pay.js') }}"></script>
</body>
</html>
