@if ($tp = session('temp_password'))
    <div class="alert alert-warning">
        <strong>Temporary password for {{ $tp['email'] }}:</strong>
        <code class="fs-5 ms-2 user-select-all">{{ $tp['password'] }}</code>
        <div class="small mt-1">Shown only once. Give it to the person directly (not by email). They must change it at first login, and every login also needs the emailed one-time code.</div>
    </div>
@endif
