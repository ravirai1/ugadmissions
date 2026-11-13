@extends('layouts.admin')
@section('title', 'Application '.$app->application_no)
@section('content')
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <span class="badge {{ $app->isPaid() ? 'badge-success' : 'badge-warning' }}">{{ $app->isPaid() ? 'Fee paid '.$app->fee_paid_at->timezone('Asia/Kolkata')->format('d M Y') : 'Fee unpaid' }}</span>
    <span class="small text-muted">Submitted {{ $app->submitted_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</span>
    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm ms-auto">Back</a>
</div>
<div class="row">
    <div class="col-xl-9 app-details">
        @include('application._details', ['docUrl' => fn ($d) => route('admin.applications.document', $d)])
    </div>
    <div class="col-xl-3">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold">Courses</h6>
                <ul class="small ps-3">
                    @foreach ($app->courses->sortBy('du_preference') as $c)
                        <li>{{ $c->course->name }} – DU pref. {{ $c->du_preference }}, CUET {{ $c->cuet_score + 0 }}
                            <br><span class="text-muted">{{ $c->course->department?->name }} · {{ $c->payment_status === 'paid' ? 'paid' : 'unpaid' }}{{ $c->added_after_payment ? ' · added later' : '' }}</span></li>
                    @endforeach
                </ul>
                @if (auth('admin')->user()->hasRole(\App\Support\Roles::ADMIN, \App\Support\Roles::ACCOUNTS))
                    <h6 class="fw-bold mt-3">Payments</h6>
                    <ul class="small ps-3 mb-0">
                        @forelse ($app->payments as $p)
                            <li><a href="{{ route('admin.payments.show', $p) }}">{{ $p->order_no }}</a> – ₹{{ number_format((float) $p->amount) }}, {{ $p->status }}</li>
                        @empty
                            <li class="text-muted">None</li>
                        @endforelse
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
