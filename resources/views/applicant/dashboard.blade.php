@extends('layouts.frontend')
@section('title', 'Dashboard')
@section('page_title', 'Applicant Dashboard')
@section('content')
<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Welcome, {{ $user->name }}</h4>
            <p class="text-muted mb-0 small">CUET No: <strong>{{ $user->cuet_no }}</strong> · Email: {{ $user->email }} · Mobile: +91 {{ $user->mobile }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="fa fa-sign-out-alt me-1"></i>Log Out</button>
        </form>
    </div>

    <div class="stage-tracker mb-5">
        @foreach ($stages as $i => $stage)
            <div class="stage {{ $i < $currentStage ? 'done' : ($i === $currentStage ? 'current' : '') }}">
                <div class="fw-bold">{{ $i + 1 }}</div>{{ $stage }}
            </div>
        @endforeach
    </div>

    @error('window')<div class="alert alert-danger">{{ $message }}</div>@enderror
    @php $win = \App\Support\AdmissionWindow::class; @endphp
    @if ($win::reopenActive())
        <div class="du-alert mb-4">
            <strong>{{ $win::status() }}</strong>
            Courses open now: {{ \App\Models\Course::whereIn('id', $win::allowedCourseIds())->orderBy('sort')->pluck('name')->implode(', ') }}.
            @if ($app?->isPaid()) You can add one of these courses if you have not applied for it, from <a href="{{ route('payment.add-courses') }}">Add courses</a>.@endif
        </div>
    @elseif (! $win::isOpen() && ! $app?->isSubmitted())
        <div class="alert alert-secondary">{{ $win::status() }}</div>
    @endif

    @foreach ($openQueries as $q)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
            <div><strong>Action needed:</strong> the scrutiny team has a query about your application ({{ $q->created_at->timezone('Asia/Kolkata')->format('d M') }}).</div>
            <a href="{{ route('applicant.query', $q) }}" class="btn btn-sm btn-primary ms-auto">View and reply</a>
        </div>
    @endforeach

    @foreach ($offers->where('status', 'offered') as $o)
        @if ($o->canAccept())
            <div class="alert alert-success d-flex flex-wrap align-items-center gap-2">
                <div><strong>Seat offered:</strong> {{ $o->course->name }} (total {{ $o->total }}/100). Accept by <strong>{{ $o->deadline_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }} IST</strong> or the seat lapses.</div>
                <a href="{{ route('applicant.offer', $o) }}" class="btn btn-sm btn-success ms-auto">View offer letter and respond</a>
            </div>
        @endif
    @endforeach

    @foreach ($callLetters as $l)
        @php
            $lPref = $app->courses->firstWhere('id', $l->application_course_id)?->du_preference;
            $heldPrefTop = $activeOffer ? $app->courses->firstWhere('id', $activeOffer->application_course_id)?->du_preference : null;
        @endphp
        {{-- only while the interview is ahead, no result yet, and no seat already held in a course placed higher --}}
        @if ($l->interview_date->endOfDay()->isFuture() && ! $l->attendance && ! $offers->firstWhere('application_course_id', $l->application_course_id) && ! ($heldPrefTop !== null && $lPref > $heldPrefTop))
            <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
                <div><strong>Called for interview:</strong> {{ $l->course->name }} on <strong>{{ $l->interview_date->format('d M Y') }}</strong>, report by {{ $l->reporting_time }}{{ $l->venue ? ' at '.$l->venue : '' }}. Bring a printed call letter and your photo ID.</div>
                <a href="{{ route('applicant.call-letter', $l) }}" class="btn btn-sm btn-primary ms-auto"><i class="fa fa-download me-1"></i>Download call letter</a>
            </div>
        @endif
    @endforeach

    @if ($callLetters->isNotEmpty() || $offers->isNotEmpty())
        <div class="p-4 border mb-4">
            <h5 class="mb-3">Interviews &amp; Results</h5>
            <table class="table table-sm mb-0">
                <thead><tr><th>Course</th><th>Interview</th><th>Result</th><th></th></tr></thead>
                @foreach ($app->courses->where('payment_status', 'paid')->sortBy('du_preference') as $choice)
                    @php
                        $letter = $callLetters->firstWhere('application_course_id', $choice->id);
                        $offer = $offers->firstWhere('application_course_id', $choice->id);
                        $heldPref = $activeOffer ? $app->courses->firstWhere('id', $activeOffer->application_course_id)?->du_preference : null;
                        $elsewhere = $activeOffer && $activeOffer->application_course_id !== $choice->id && $choice->du_preference > $heldPref;
                        $canMoveUp = $activeOffer && $activeOffer->application_course_id !== $choice->id && $choice->du_preference < $heldPref;
                    @endphp
                    <tr class="{{ $elsewhere ? 'text-muted' : '' }}">
                        <td>{{ $choice->course->name }} <span class="small text-muted">(DU pref. {{ $choice->du_preference }})</span></td>
                        <td class="small">@if ($letter){{ $letter->interview_date->format('d M Y') }}, {{ $letter->reporting_time }}@if ($letter->attendance) · {{ ucfirst($letter->attendance) }}@endif @else Not called yet @endif</td>
                        <td class="small">
                            @if ($offer)
                                <strong>{{ \App\Models\SeatOffer::STATUSES[$offer->status] }}</strong> · {{ $offer->total }}/100
                            @elseif ($elsewhere)
                                Already selected – first preference ({{ $activeOffer->course->name }})
                            @elseif ($canMoveUp)
                                Waiting list: you will be moved up if a seat opens
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($elsewhere)
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>No action</button>
                            @elseif ($offer)
                                <a href="{{ route('applicant.offer', $offer) }}" class="btn btn-sm btn-outline-success">Offer letter</a>
                            @elseif ($letter)
                                <a href="{{ route('applicant.call-letter', $letter) }}" class="btn btn-sm btn-outline-primary">Call letter</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
            <p class="small text-muted mt-2 mb-0">Merit is out of 100: 85 from your verified CUET score and 15 from the interview. If you are selected in more than one course, you get the seat in the course you put first in your DU preferences. If a seat opens later in a course you placed higher, you are moved up automatically.</p>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="p-4 border h-100">
                <h5 class="mb-3">Your Application</h5>
                @if (! $app)
                    <p class="text-muted">You have not started your application yet.</p>
                    <a href="{{ route('application.start') }}" class="btn btn-primary py-2 px-4"><i class="fa fa-edit me-2"></i>Apply Now</a>
                @elseif (! $app->isSubmitted())
                    <p class="mb-2">Application in progress: <strong>{{ $progress }}%</strong> complete.</p>
                    <div class="progress mb-3" style="height:8px"><div class="progress-bar bg-primary" style="width: {{ $progress }}%"></div></div>
                    <a href="{{ route('application.start') }}" class="btn btn-primary py-2 px-4"><i class="fa fa-edit me-2"></i>Continue Application</a>
                @else
                    <p class="mb-1">Application no.: <strong>{{ $app->application_no }}</strong></p>
                    <p class="mb-2 small text-muted">Submitted {{ $app->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</p>
                    <ul class="small">
                        @foreach ($app->courses as $c)<li>{{ $c->course->name }} (DU pref. {{ $c->du_preference }})</li>@endforeach
                        @if ($app->residence)<li>Residence</li>@endif
                    </ul>
                    @if ($app->isPaid())
                        <div class="alert alert-success py-2 small mb-3"><i class="fa fa-check-circle me-1"></i>Application fee paid on {{ $app->fee_paid_at->timezone('Asia/Kolkata')->format('d M Y') }}. Your application is with the College for scrutiny.</div>
                        <a href="{{ route('payment.index') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i>Add course / residence</a>
                    @else
                        <div class="alert alert-warning py-2 small mb-3">Fee payment pending. Your application is complete only after the fee is paid.</div>
                        <a href="{{ route('payment.index') }}" class="btn btn-primary btn-sm"><i class="fa fa-rupee-sign me-1"></i>Pay Application Fee</a>
                    @endif
                    <a href="{{ route('application.step', 'preview') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-eye me-1"></i>View Application</a>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="p-4 bg-light h-100">
                @if ($scrutinies->isNotEmpty())
                    <h5 class="mb-3">Scrutiny status</h5>
                    <ul class="small mb-4 ps-3">
                        @foreach ($scrutinies as $sc)
                            <li>{{ $sc->type === 'academic' ? $sc->applicationCourse?->course->name : 'Religious (Christian category)' }}:
                                <strong>{{ $sc->status === 'pending' ? 'Under scrutiny' : $sc->statusLabel() }}</strong>@if ($sc->status === 'rejected' && $sc->remarks)<br><span class="text-muted">{{ $sc->remarks }}</span>@endif</li>
                        @endforeach
                    </ul>
                @endif
                <h5 class="mb-3">Notices</h5>
                @forelse ($notices as $n)
                    <div class="small mb-3">
                        <strong>@if ($n->is_pinned)<i class="fa fa-thumbtack text-primary me-1"></i>@endif{{ $n->title }}</strong>
                        <span class="text-muted">· {{ $n->published_at?->timezone('Asia/Kolkata')->format('d M') }}</span>
                        <div class="text-muted">{{ \Illuminate\Support\Str::limit($n->body, 160) }}</div>
                    </div>
                @empty
                    <p class="small text-muted mb-0">No notices yet.</p>
                @endforelse
                @if ($notices->isNotEmpty())<a href="{{ route('notices') }}" class="small">All notices</a>@endif
            </div>
        </div>
    </div>

    @if ($queries->isNotEmpty())
        <div class="p-4 border mt-4" id="queries">
            <h5 class="mb-1">Queries from the scrutiny team</h5>
            <p class="small text-muted">The same query is emailed to you with a secure link. You can reply and upload documents either from that link or from here.</p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle small mb-0">
                    <thead class="table-light"><tr><th>Raised</th><th>About</th><th>Query</th><th>Your reply</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($queries as $q)
                            <tr>
                                <td class="text-nowrap">{{ $q->created_at->timezone('Asia/Kolkata')->format('d M Y') }}</td>
                                <td>{{ $q->scrutiny?->type === 'religious' ? 'Christian category' : ($q->scrutiny?->applicationCourse?->course->name ?? 'Application') }}</td>
                                <td style="white-space: pre-line">{{ \Illuminate\Support\Str::limit($q->message, 200) }}@if ($q->document_types)<br><span class="text-muted">Documents asked: {{ collect($q->document_types)->map(fn ($t) => \App\Support\DocumentTypes::label($t))->implode(', ') }}</span>@endif</td>
                                <td>
                                    @if ($q->replied_at)
                                        <span class="text-muted">{{ $q->replied_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</span>
                                        @if ($q->reply)<div style="white-space: pre-line">{{ \Illuminate\Support\Str::limit($q->reply, 200) }}</div>@endif
                                        @if ($q->attachments->isNotEmpty())<div><i class="fa fa-paperclip me-1"></i>{{ $q->attachments->count() }} {{ \Illuminate\Support\Str::plural('document', $q->attachments->count()) }} uploaded</div>@endif
                                    @else
                                        <span class="text-muted">Not replied yet</span>
                                    @endif
                                </td>
                                <td>
                                    @php([$qc, $ql] = match ($q->status) { 'open' => ['bg-warning text-dark', 'Reply needed'], 'answered' => ['bg-info text-dark', 'Replied, under review'], default => ['bg-success', 'Closed'] })
                                    <span class="badge {{ $qc }}">{{ $ql }}</span>
                                </td>
                                <td class="text-nowrap"><a href="{{ route('applicant.query', $q) }}" class="btn btn-sm {{ $q->isOpen() ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $q->isOpen() ? 'Reply and upload' : 'View' }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($app?->isSubmitted())
        <div class="p-4 border mt-4" id="payments">
            <h5 class="mb-1">Track payments &amp; receipts</h5>
            @php($tries = $payments->count())
            @if ($tries)
                <p class="small text-muted">{{ $tries }} payment {{ \Illuminate\Support\Str::plural('attempt', $tries) }}, {{ $payments->filter->isSuccess()->count() }} successful. Open <em>SBI updates</em> on any row to see each message from SBI ePay (return to this site, SBI server push, status checks).</p>
            @endif
            @include('payment._history', ['payments' => $payments])
        </div>
    @endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('frontend/js/ssc-pay.js') }}"></script>
@endpush
