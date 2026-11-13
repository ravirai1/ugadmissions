@if (\App\Services\InterviewService::isLocked($l->course_id))
    <span class="small text-muted">Finalised</span>
@else
    <form method="POST" action="{{ route('admin.attendance.mark', $l) }}" class="d-inline">@csrf<input type="hidden" name="attendance" value="present"><button class="btn btn-sm {{ $l->attendance === 'present' ? 'btn-success' : 'btn-outline-success' }}" type="submit"><i class="fas fa-check me-1"></i>Present</button></form>
    <form method="POST" action="{{ route('admin.attendance.mark', $l) }}" class="d-inline">@csrf<input type="hidden" name="attendance" value="absent"><button class="btn btn-sm {{ $l->attendance === 'absent' ? 'btn-danger' : 'btn-outline-danger' }}" type="submit"><i class="fas fa-times me-1"></i>Absent</button></form>
@endif
