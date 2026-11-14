@extends('layouts.admin')
@section('title', ($s->type === 'academic' ? 'Academic Scrutiny – '.$s->applicationCourse->course->name : 'Religious Scrutiny').' – '.$app->application_no)
@section('content')
@php
    $badge = ['pending' => 'warning', 'query' => 'info', 'approved' => 'success', 'rejected' => 'danger'];
    $doc = fn ($t) => isset($uploaded[$t]) ? '<a href="'.e(route('admin.applications.document', $uploaded[$t])).'" target="_blank" rel="noopener">'.e(\App\Support\DocumentTypes::label($t)).'</a>' : '<span class="text-danger">'.e(\App\Support\DocumentTypes::label($t)).' (missing)</span>';
    $keyDocs = $s->type === 'academic' ? ['class12', 'cuet_scorecard', 'csas_preference', 'class10', 'photo'] : ['baptism', 'parent_baptism', 'church_letter', 'st_certificate'];
    $b = $app->baptism_details ?? [];
@endphp
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <span class="badge badge-{{ $badge[$s->status] ?? 'secondary' }}">{{ $s->statusLabel() }}</span>
    @if ($s->reply_received)<span class="badge badge-primary">Applicant replied</span>@endif
    <span class="small text-muted">{{ $user->name }} · CUET {{ $user->cuet_no }} · {{ $app->gender }}</span>
    @if ($s->reviewed_at)<span class="small text-muted">· last action by {{ $s->reviewer?->name }} on {{ $s->reviewed_at->timezone('Asia/Kolkata')->format('d M Y h:i A') }}</span>@endif
    <a href="{{ route('admin.scrutiny.index', $s->type) }}" class="btn btn-outline-secondary btn-sm ms-auto">Back to queue</a>
</div>

<div class="row">
    <div class="col-xl-8">
        @if ($s->type === 'academic')
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Academic mapping – {{ $s->applicationCourse->course->name }}</h5>
                    <p class="small mb-2">
                        DU pref. <strong>{{ $s->applicationCourse->du_preference }}</strong>
                        @if ($s->applicationCourse->combination) · {{ $s->applicationCourse->combination }}@endif
                        · Class 12 aggregate <strong>{{ $app->class12Percentage() }}%</strong>
                        @if (! empty($rules['min_class12_percent'])) (course needs {{ $rules['min_class12_percent'] }}%)@endif
                    </p>
                    @if ($eval['eligible'])
                        <div class="alert alert-success py-2 small mb-2"><i class="fas fa-check me-1"></i>Meets the DU rule for this course on the data entered.</div>
                    @else
                        <div class="alert alert-danger py-2 small mb-2"><strong>Does not meet the rule on the data entered:</strong> {{ implode('; ', $eval['reasons']) }}</div>
                    @endif
                    @if ($unmatched)
                        <div class="alert alert-warning py-2 small mb-2">CUET domain paper(s) with no matching Class 12 subject: {{ collect($unmatched)->pluck('name')->implode(', ') }}</div>
                    @endif
                    <table class="table table-sm table-bordered small">
                        <thead class="table-light"><tr><th>CUET paper counted</th><th>Type</th><th class="text-end">CUET score</th><th>Matching Class 12 subject</th><th class="text-end">Class 12 marks</th></tr></thead>
                        @foreach ($mapping as $m)
                            <tr class="{{ $m['needsMatch'] && ! $m['class12'] ? 'table-danger' : '' }}">
                                <td>{{ $m['paper'] }}</td>
                                <td>{{ ucfirst($m['type']) }}</td>
                                <td class="text-end">{{ $m['score'] }}</td>
                                <td>{{ $m['class12']?->class12Subject->name ?? ($m['needsMatch'] ? 'None' : '–') }}</td>
                                <td class="text-end">{{ $m['class12'] ? ($m['class12']->marks_obtained + 0).' / '.$m['class12']->max_marks : '' }}</td>
                            </tr>
                        @endforeach
                        <tr class="fw-bold"><td colspan="2">CUET total (system)</td><td class="text-end">{{ number_format($computedScore, 2) }}</td><td colspan="2"></td></tr>
                    </table>
                    <details class="small">
                        <summary>Course rule and all CUET / Class 12 entries</summary>
                        <pre class="bg-light p-2 mt-2 small">{{ json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        <div class="row">
                            <div class="col-md-6"><table class="table table-sm"><thead><tr><th>CUET paper</th><th>Score</th></tr></thead>@foreach ($app->cuetScores as $c)<tr><td>{{ $c->subject->name }}</td><td>{{ $c->score }}</td></tr>@endforeach</table></div>
                            <div class="col-md-6"><table class="table table-sm"><thead><tr><th>Class 12 subject</th><th>Marks</th></tr></thead>@foreach ($app->class12Marks as $m)<tr><td>{{ $m->class12Subject->name }}</td><td>{{ $m->marks_obtained + 0 }}/{{ $m->max_marks }}</td></tr>@endforeach</table></div>
                        </div>
                    </details>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Religious details</h5>
                    <table class="table table-sm table-bordered small mb-0">
                        <tr><th style="width:35%">Category</th><td>Christian – {{ $categoryNames[$app->christian_subcategory] ?? $app->christian_subcategory }}{{ $app->is_cst ? ' (CST – Christian ST)' : '' }}</td></tr>
                        @include('application._baptism-rows')
                    </table>
                </div>
            </div>
        @endif

        @if ($canDecide)
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Decision</h5>
                    @if ($s->isDecided())<div class="alert alert-info py-2 small">Already decided. As admin you can change the decision.</div>@endif
                    <form method="POST" action="{{ route('admin.scrutiny.decide', $s) }}">
                        @csrf
                        <div class="mb-3">
                            @foreach ($checklist as $key => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="checklist[]" value="{{ $key }}" id="ck-{{ $key }}" @checked(in_array($key, old('checklist', $s->checklist ?? []), true))>
                                    <label class="form-check-label small" for="ck-{{ $key }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        @if ($s->type === 'academic')
                            <div class="mb-3" style="max-width: 320px">
                                <label class="form-label small" for="verified_cuet_score">Verified CUET total (from the scorecard)</label>
                                <input type="number" step="0.01" min="0" max="1000" class="form-control form-control-sm" id="verified_cuet_score" name="verified_cuet_score" value="{{ old('verified_cuet_score', $s->verified_cuet_score ?? $computedScore) }}">
                                <div class="form-text">Used for the 85% CUET part of the merit score.</div>
                            </div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label small" for="remarks">Remarks (required if rejecting)</label>
                            <textarea class="form-control form-control-sm" id="remarks" name="remarks" rows="2" maxlength="2000">{{ old('remarks', $s->remarks) }}</textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success btn-sm" type="submit" name="status" value="approved"><i class="fas fa-check me-1"></i>Approve</button>
                            <button class="btn btn-danger btn-sm" type="submit" name="status" value="rejected"><i class="fas fa-times me-1"></i>{{ $s->type === 'religious' ? 'Not accepted (treat as General)' : 'Reject' }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Raise a query</h5>
                    <p class="small text-muted">The applicant gets an email with a secure link (valid {{ \App\Services\ScrutinyService::QUERY_LINK_DAYS }} days) to reply and upload. Their files are added to the profile, and the application comes back to this queue marked "Reply received".</p>
                    <form method="POST" action="{{ route('admin.scrutiny.query', $s) }}">
                        @csrf
                        <textarea class="form-control form-control-sm mb-2" name="message" rows="3" maxlength="2000" placeholder="What is missing or unclear?" required>{{ old('message') }}</textarea>
                        <div class="row small mb-2">
                            @foreach ($docTypes as $key => $label)
                                <div class="col-md-6"><label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="document_types[]" value="{{ $key }}">{{ $label }}</label></div>
                            @endforeach
                        </div>
                        <button class="btn btn-info btn-sm" type="submit"><i class="fas fa-envelope me-1"></i>Send query</button>
                    </form>
                </div>
            </div>
        @else
            <div class="alert alert-secondary small">This decision is locked. An admin can reopen it.</div>
        @endif

        @if ($s->queries->isNotEmpty())
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Queries</h5>
                    @foreach ($s->queries as $q)
                        <div class="border rounded p-2 mb-2 small">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $q->raisedBy?->name }} · {{ $q->created_at->timezone('Asia/Kolkata')->format('d M Y h:i A') }}</strong>
                                <span class="badge badge-{{ $q->status === 'open' ? 'warning' : ($q->status === 'answered' ? 'primary' : 'secondary') }}">{{ ucfirst($q->status) }}</span>
                            </div>
                            <p class="mb-1">{{ $q->message }}</p>
                            @if ($q->document_types)<p class="mb-1 text-muted">Asked for: {{ collect($q->document_types)->map(fn ($t) => \App\Support\DocumentTypes::label($t))->implode(', ') }}</p>@endif
                            @if ($q->replied_at)
                                <div class="bg-light p-2 mt-1">
                                    <strong>Reply {{ $q->replied_at->timezone('Asia/Kolkata')->format('d M Y h:i A') }}:</strong> {{ $q->reply ?: '(no message)' }}
                                    @foreach ($q->attachments as $d)
                                        <br><a href="{{ route('admin.applications.document', $d) }}" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> {{ \App\Support\DocumentTypes::label($d->type) }}</a>
                                    @endforeach
                                </div>
                            @elseif ($q->isOpen())
                                <span class="text-muted">Waiting for reply · link valid until {{ $q->expires_at?->timezone('Asia/Kolkata')->format('d M Y') }} · reminders sent: {{ $q->reminder_count }}</span>
                            @endif
                            @if ($q->status !== 'closed')
                                <form method="POST" action="{{ route('admin.scrutiny.query.close', $q) }}" class="mt-1">@csrf<button class="btn btn-link btn-sm p-0" type="submit">Close query</button></form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-body small">
                <h6 class="fw-bold">Documents to check</h6>
                <ul class="ps-3">@foreach ($keyDocs as $t)@if ($t !== 'st_certificate' || $app->is_cst)<li>{!! $doc($t) !!}</li>@endif @endforeach</ul>
                <h6 class="fw-bold mt-3">All scrutiny for this applicant</h6>
                <ul class="ps-3 mb-0">
                    @foreach ($app->scrutinies as $other)
                        <li>{{ $other->type === 'academic' ? $other->applicationCourse?->course->name : 'Religious' }}: <span class="badge badge-{{ $badge[$other->status] ?? 'secondary' }}">{{ $other->statusLabel() }}</span></li>
                    @endforeach
                </ul>
                @if ($isAdmin && $s->isDecided())
                    <form method="POST" action="{{ route('admin.scrutiny.reopen', $s) }}" class="mt-3">@csrf<button class="btn btn-outline-warning btn-sm" type="submit">Reopen for review</button></form>
                @endif
            </div>
        </div>
    </div>
</div>

<details class="mt-2">
    <summary class="fw-bold mb-2">Full application</summary>
    <div class="app-details">
        @include('application._details', ['docUrl' => fn ($d) => route('admin.applications.document', $d)])
    </div>
</details>
@endsection
