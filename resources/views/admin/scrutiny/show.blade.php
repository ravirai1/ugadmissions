@extends('layouts.admin')
@section('title', ($s->type === 'academic' ? 'Academic Scrutiny – '.$s->applicationCourse->course->name : 'Religious Scrutiny').' – '.$app->application_no)
@section('content')
@php
    $badge = ['pending' => 'warning', 'query' => 'info', 'review' => 'primary', 'approved' => 'success', 'rejected' => 'danger'];
    $doc = fn ($t) => isset($uploaded[$t]) ? '<a href="'.e(route('admin.applications.document', $uploaded[$t])).'" target="_blank" rel="noopener">'.e(\App\Support\DocumentTypes::label($t)).'</a>' : '<span class="text-danger">'.e(\App\Support\DocumentTypes::label($t)).' (missing)</span>';
    $keyDocs = $s->type === 'academic' ? ['class12', 'cuet_scorecard', 'class10', 'csas_preference', 'photo'] : ['baptism', 'parent_baptism', 'church_letter', 'st_certificate'];
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
    <div class="col-xl-7">
        @if ($s->type === 'religious')
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Applicant</h5>
                    <div class="d-flex gap-3">
                        @if (isset($uploaded['photo']))<img src="{{ route('admin.applications.document', $uploaded['photo']) }}" alt="Photo" style="width:96px;height:116px;object-fit:cover;border:1px solid #ddd">@endif
                        <table class="table table-sm table-compact table-bordered small mb-0">
                            <tr><th style="width:32%">Name</th><td><strong>{{ $user->name }}</strong> · {{ $app->application_no }}</td></tr>
                            <tr><th>Date of birth / gender</th><td>{{ $app->dob?->format('d M Y') }} · {{ $app->gender }}</td></tr>
                            <tr><th>Father</th><td>{{ \App\Support\PersonalDetails::withTitle($app->father_title, $app->father_name) }}</td></tr>
                            <tr><th>Mother</th><td>{{ \App\Support\PersonalDetails::withTitle($app->mother_title, $app->mother_name) }}</td></tr>
                            <tr><th>Contact</th><td>+91 {{ $user->mobile }} · {{ $user->email }}</td></tr>
                            <tr><th>Address</th><td>{{ collect([$app->cur_line1, $app->cur_line2, $app->cur_city, $app->curDistrict?->name ?? $app->cur_district_other, $app->curState?->name, $app->cur_pin])->filter()->implode(', ') }}</td></tr>
                            <tr><th>State you belong to</th><td>{{ $app->domicileState?->name }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        @endif
        @if ($s->type === 'academic')
            @php $choice = $s->applicationCourse; @endphp
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Academic record <span class="small text-muted fw-normal">as entered by the applicant</span></h5>
                    <table class="table table-sm table-compact table-bordered small mb-3">
                        <tr><th style="width:30%">Course applied</th><td><strong>{{ $choice->course->name }}</strong> · DU preference {{ $choice->du_preference }}</td></tr>
                        <tr><th>Subject combination</th><td>@if ($choice->combination)<strong>{{ $choice->combination }}</strong>@else<span class="text-muted">Not applicable for this course</span>@endif</td></tr>
                        <tr><th>Class 10</th><td>{{ $app->c10_board }}, {{ $app->c10_year }} · Roll {{ $app->c10_roll_no }} · {{ $app->c10_obtained + 0 }} / {{ $app->c10_max }} (<strong>{{ $app->class10Percentage() }}%</strong>) · {!! $doc('class10') !!}</td></tr>
                        <tr><th>Class 12</th><td>{{ $app->c12_board }}, {{ $app->c12_year }} · Roll {{ $app->c12_roll_no }} · Aggregate <strong>{{ $app->class12Percentage() }}%</strong> · {!! $doc('class12') !!}</td></tr>
                        <tr><th>CUET (UG)</th><td>Roll {{ $app->cuet_roll_no }} · {!! $doc('cuet_scorecard') !!} · {!! $doc('csas_preference') !!}</td></tr>
                    </table>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-compact small">
                                <thead class="table-light"><tr><th>Class 12 subject</th><th class="text-end">Marks</th><th class="text-end">%</th></tr></thead>
                                @foreach ($app->class12Marks as $m)
                                    <tr><td>{{ $m->class12Subject->name }}</td><td class="text-end">{{ $m->marks_obtained + 0 }} / {{ $m->max_marks }}</td><td class="text-end">{{ $m->max_marks ? round($m->marks_obtained / $m->max_marks * 100, 1) : '' }}</td></tr>
                                @endforeach
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-compact small">
                                <thead class="table-light"><tr><th>CUET paper</th><th class="text-end">Score</th><th></th></tr></thead>
                                @foreach ($app->cuetScores as $c)
                                    <tr class="{{ in_array($c->subject_id, $choice->papers ?? []) ? 'fw-bold' : '' }}"><td>{{ $c->subject->name }}</td><td class="text-end">{{ $c->score + 0 }}</td><td>@if (in_array($c->subject_id, $choice->papers ?? []))<span class="badge badge-success">counted</span>@endif</td></tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
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
                    <div class="table-responsive"><table class="table table-sm table-compact table-bordered small">
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
                    </table></div>
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

        @if ($s->status === 'review' || $s->review_comment)
            <div class="alert alert-warning small">
                <strong>Marked for review</strong> by {{ $s->reviewFlaggedBy?->name }} on {{ $s->review_at?->timezone('Asia/Kolkata')->format('d M Y h:i A') }}:<br>{{ $s->review_comment }}
            </div>
        @endif
        @if ($s->status === 'rejected' && $s->remarks)
            <div class="alert alert-danger small"><strong>Final comment (TFA admin):</strong> {{ $s->remarks }}</div>
        @endif

        <form method="POST" action="{{ route('admin.scrutiny.decide', $s) }}">
            @csrf
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Verify the data</h5>
                    <p class="small text-muted mb-2">The applicant's entries cannot be changed. Check each item against the documents: tick <strong>OK</strong> if it matches, or type the value you found. Your values are saved separately and used for the merit list.</p>
                    @error('verify')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror
                    <div class="table-responsive">
                    <table class="table table-sm table-compact table-bordered small align-middle mb-2">
                        <thead class="table-light"><tr><th>Item</th><th>Applicant entered</th><th class="text-center">OK</th><th style="min-width: 170px">Value found in the document</th><th>Verified</th></tr></thead>
                        @foreach (collect($fields)->groupBy('group', true) as $group => $items)
                            <tr class="table-light"><td colspan="5" class="fw-bold">{{ $group }}</td></tr>
                            @foreach ($items as $key => $f)
                                @php $v = $verified[$key] ?? null; $shown = fn ($x) => $f['type'] === 'select' ? ($f['options'][$x] ?? $x) : ($f['type'] === 'date' && $x ? \Illuminate\Support\Carbon::parse($x)->format('d M Y') : $x); @endphp
                                <tr class="{{ $v?->result === 'corrected' ? 'table-warning' : '' }}">
                                    <td>{{ $s->type === 'academic' ? \Illuminate\Support\Str::after($f['label'], ': ') : $f['label'] }}</td>
                                    <td>{{ $shown($f['value']) }}</td>
                                    @if ($canDecide)
                                        <td class="text-center"><input class="form-check-input" type="checkbox" name="verify[{{ $key }}][ok]" value="1" aria-label="{{ $f['label'] }} is correct" @checked(old("verify.$key.ok"))></td>
                                        <td>
                                            @if ($f['type'] === 'select')
                                                <select class="form-select form-select-sm @error("verify.$key.value") is-invalid @enderror" name="verify[{{ $key }}][value]" aria-label="{{ $f['label'] }} as found">
                                                    <option value=""></option>
                                                    @foreach ($f['options'] as $ok => $ol)<option value="{{ $ok }}" @selected(old("verify.$key.value") === (string) $ok)>{{ $ol }}</option>@endforeach
                                                </select>
                                            @else
                                                <input class="form-control form-control-sm @error("verify.$key.value") is-invalid @enderror" name="verify[{{ $key }}][value]" type="{{ $f['type'] === 'number' ? 'number' : ($f['type'] === 'date' ? 'date' : 'text') }}" @if ($f['type'] === 'number') step="0.01" min="{{ $f['min'] }}" max="{{ $f['max'] }}" @endif value="{{ old("verify.$key.value") }}" aria-label="{{ $f['label'] }} as found">
                                            @endif
                                            @error("verify.$key.value")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </td>
                                    @else
                                        <td></td><td></td>
                                    @endif
                                    <td>
                                        @if ($v)
                                            @if ($v->result === 'ok')<span class="text-success"><i class="fas fa-check"></i> OK</span>@else<strong>{{ $shown($v->verified_value) }}</strong> <span class="badge badge-warning">differs</span>@endif
                                            <br><span class="text-muted">{{ $v->verifier?->name }}</span>
                                        @else
                                            <span class="text-muted">Not yet</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </table>
                    </div>
                    @php $left = count($fields) - $verified->count(); @endphp
                    <p class="small mb-0">{{ $verified->count() }} of {{ count($fields) }} items verified{{ $left ? ", {$left} to go" : '' }}.
                        @if ($s->type === 'academic' && $s->verified_cuet_score !== null) Verified CUET total: <strong>{{ $s->verified_cuet_score + 0 }}</strong>@endif @if ($s->type === 'academic' && $s->verified_class12_percent !== null) · Class 12: <strong>{{ $s->verified_class12_percent + 0 }}%</strong>@endif
                        @if ($s->type === 'religious' && $s->verified_subcategory) Verified sub-category: <strong>{{ $categoryNames[$s->verified_subcategory] ?? $s->verified_subcategory }}</strong>{{ $s->verified_cst ? ' (CST)' : '' }}@endif
                    </p>
                </div>
            </div>

            @if ($canDecide)
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Decision</h5>
                    @error('action')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror
                    @if ($isTfa)
                        <div class="alert alert-info py-2 small">You are the TFA admin: you have the final say, and only you can reject.</div>
                    @endif
                    <div class="mb-3">
                        @foreach ($checklist as $key => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="checklist[]" value="{{ $key }}" id="ck-{{ $key }}" @checked(in_array($key, old('checklist', $s->checklist ?? []), true))>
                                <label class="form-check-label small" for="ck-{{ $key }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="review_comment">Comment for the TFA admin (needed to mark for review)</label>
                        <textarea class="form-control form-control-sm @error('review_comment') is-invalid @enderror" id="review_comment" name="review_comment" rows="2" maxlength="2000">{{ old('review_comment', $s->review_comment) }}</textarea>
                    </div>
                    @if ($isTfa)
                        <div class="mb-3">
                            <label class="form-label small" for="remarks">Final comment (needed to reject)</label>
                            <textarea class="form-control form-control-sm @error('remarks') is-invalid @enderror" id="remarks" name="remarks" rows="2" maxlength="2000">{{ old('remarks', $s->remarks) }}</textarea>
                        </div>
                    @endif
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-outline-primary btn-sm" type="submit" name="action" value="save"><i class="fas fa-save me-1"></i>Save verification</button>
                        <button class="btn btn-success btn-sm" type="submit" name="action" value="approved"><i class="fas fa-check me-1"></i>Approve</button>
                        @unless ($s->status === 'review')
                            <button class="btn btn-warning btn-sm" type="submit" name="action" value="review"><i class="fas fa-flag me-1"></i>Mark for review</button>
                        @endunless
                        @if ($isTfa)
                            <button class="btn btn-danger btn-sm" type="submit" name="action" value="rejected"><i class="fas fa-times me-1"></i>{{ $s->type === 'religious' ? 'Reject (treat as General)' : 'Reject' }}</button>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </form>

        @if ($canDecide)
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
            <div class="alert alert-secondary small">{{ $s->status === 'review' ? 'This is with the TFA admin for review.' : 'This decision is final. The TFA admin can reopen it.' }}</div>
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

    <div class="col-xl-5">
        <div class="doc-viewer-sticky">
        @php $viewDocs = collect($keyDocs)->filter(fn ($t) => isset($uploaded[$t])); @endphp
        @if ($viewDocs->isNotEmpty())
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-2">Documents</h5>
                    <ul class="nav nav-tabs small" role="tablist">
                        @foreach ($viewDocs as $t)
                            <li class="nav-item" role="presentation"><button class="nav-link py-1 px-2 {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#doc-{{ $t }}" type="button" role="tab">{{ ['class10' => 'Class 10', 'class12' => 'Class 12', 'cuet_scorecard' => 'CUET result', 'csas_preference' => 'CSAS', 'photo' => 'Photo', 'baptism' => 'Baptism', 'parent_baptism' => "Parent's baptism", 'church_letter' => 'Church letter', 'st_certificate' => 'ST cert.'][$t] ?? $t }}</button></li>
                        @endforeach
                    </ul>
                    <div class="tab-content border border-top-0 p-1">
                        @foreach ($viewDocs as $t)
                            @php $d = $uploaded[$t]; $src = route('admin.applications.document', $d); @endphp
                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="doc-{{ $t }}" role="tabpanel">
                                @if (str_starts_with($d->mime, 'image/'))
                                    <img src="{{ $src }}" alt="{{ \App\Support\DocumentTypes::label($t) }}" class="doc-view-img">
                                @else
                                    <iframe src="{{ $src }}" title="{{ \App\Support\DocumentTypes::label($t) }}" class="doc-view-frame" loading="lazy"></iframe>
                                @endif
                                <a href="{{ $src }}" target="_blank" rel="noopener" class="small"><i class="fas fa-external-link-alt me-1"></i>Open in a new tab</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
        <div class="card">
            <div class="card-body small">
                <h6 class="fw-bold">Documents to check</h6>
                <ul class="ps-3">@foreach ($keyDocs as $t)@if (($t !== 'st_certificate' || $app->is_cst) && in_array($t, $required, true) || isset($uploaded[$t]))<li>{!! $doc($t) !!}</li>@endif @endforeach</ul>
                <h6 class="fw-bold mt-3">All scrutiny for this applicant</h6>
                <ul class="ps-3 mb-0">
                    @foreach ($app->scrutinies->when($religiousOnly, fn ($c) => $c->where('type', 'religious')) as $other)
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
</div>

@unless ($religiousOnly)
<details class="mt-2">
    <summary class="fw-bold mb-2">Full application</summary>
    <div class="app-details">
        @include('application._details', ['docUrl' => fn ($d) => route('admin.applications.document', $d)])
    </div>
</details>
@endunless
@endsection
