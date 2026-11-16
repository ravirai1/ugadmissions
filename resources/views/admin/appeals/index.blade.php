@extends('layouts.admin')
@section('title', 'Appeals')
@section('content')
<div class="card">
    <div class="card-body">
        <p class="small text-muted">Applicants can appeal once within {{ config('admissions.appeal_days') }} days of a scrutiny rejection. Only the TFA admin decides. Accepting an appeal sends the record back to the scrutiny queue; rejecting it is final.</p>
        <ul class="nav nav-pills mb-3">
            @foreach (['pending' => 'Pending', 'allowed' => 'Accepted', 'upheld' => 'Rejected', 'all' => 'All'] as $k => $l)
                <li class="nav-item"><a class="nav-link {{ $status === $k ? 'active' : '' }}" href="?status={{ $k }}">{{ $l }} @if ($k !== 'all')({{ $counts[$k] ?? 0 }})@endif</a></li>
            @endforeach
        </ul>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead><tr><th>Received</th><th>Application</th><th>Applicant</th><th>Scrutiny</th><th>Status</th><th>Decided by</th><th></th></tr></thead>
                <tbody>
                @forelse ($appeals as $a)
                    <tr>
                        <td class="text-nowrap">{{ $a->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td>
                        <td>{{ $a->application->application_no }}</td>
                        <td>{{ $a->application->user->name }}</td>
                        <td>{{ $a->scrutiny->type === 'religious' ? 'Religious' : 'Academic: '.$a->scrutiny->applicationCourse?->course->name }}</td>
                        <td><span class="badge {{ ['pending' => 'bg-warning text-dark', 'allowed' => 'bg-success', 'upheld' => 'bg-secondary'][$a->status] }}">{{ $a->statusLabel() }}</span></td>
                        <td>{{ $a->decidedBy?->name }}</td>
                        <td><a href="{{ route('admin.appeals.show', $a) }}" class="btn btn-sm btn-primary">{{ $a->isPending() ? 'Review' : 'View' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No appeals.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $appeals->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
