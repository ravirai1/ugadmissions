<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\CheckFileIntegrity;
use App\Http\Controllers\Controller;
use App\Models\ApplicantActivityLog;
use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\SecurityEvent;
use App\Models\StaffActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SecurityController extends Controller
{
    public function attempts(Request $request)
    {
        $attempts = LoginAttempt::query()
            ->when($request->filled('guard'), fn ($q) => $q->where('guard', $request->guard))
            ->when($request->filled('status'), fn ($q) => $q->where('successful', $request->status === 'success'))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('ip', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%')))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.security.attempts', compact('attempts'));
    }

    public function blocked()
    {
        $blocked = BlockedIp::active()->latest()->paginate(50);

        return view('admin.security.blocked', compact('blocked'));
    }

    public function block(Request $request)
    {
        $data = $request->validate([
            'ip' => ['required', 'ip'],
            'guard' => ['required', 'in:admin,applicant,site'],
            'reason' => ['nullable', 'string', 'max:200'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ]);

        $row = BlockedIp::updateOrCreate(
            ['ip' => $data['ip'], 'guard' => $data['guard']],
            [
                'reason' => $data['reason'] ?? 'Blocked manually',
                'blocked_until' => isset($data['minutes']) ? now()->addMinutes($data['minutes']) : null,
                'blocked_by' => $request->user('admin')->id,
            ]
        );
        AuditLog::record('security.ip_blocked', $row, $data);
        Cache::forget('site-block:'.$data['ip']);

        return back()->with('status', "IP {$data['ip']} blocked.");
    }

    public function unblock(BlockedIp $blockedIp)
    {
        AuditLog::record('security.ip_unblocked', null, ['ip' => $blockedIp->ip, 'guard' => $blockedIp->guard]);
        $blockedIp->delete();
        Cache::forget('site-block:'.$blockedIp->ip);

        return back()->with('status', "IP {$blockedIp->ip} unblocked.");
    }

    public function audit(Request $request)
    {
        $logs = AuditLog::with('admin')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->action.'%'))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.security.audit', compact('logs'));
    }

    /** Applicant activity log: logins, logouts, failed logins, every form save/upload/payment, letters and receipts opened. */
    public function applicantActivity(Request $request)
    {
        $logs = $this->filterActivity($request, ApplicantActivityLog::with('user'), 'user', ['name', 'email', 'cuet_no', 'mobile']);

        return view('admin.security.activity', ['logs' => $logs, 'who' => 'applicant', 'title' => 'Applicant Activity Log']);
    }

    /** Staff activity log: logins, logouts, failed logins and every page opened or action taken in the staff panel. */
    public function staffActivity(Request $request)
    {
        $logs = $this->filterActivity($request, StaffActivityLog::with('admin'), 'admin', ['name', 'email']);

        return view('admin.security.activity', ['logs' => $logs, 'who' => 'staff', 'title' => 'Staff Activity Log']);
    }

    /** Attack attempts caught by the DetectAttacks middleware (SQL injection, script injection, path probing ...). */
    public function threats(Request $request)
    {
        $data = $request->validate(['type' => ['nullable', 'in:'.implode(',', array_keys(SecurityEvent::TYPES))], 'q' => ['nullable', 'string', 'max:100']]);
        $events = SecurityEvent::query()
            ->when($data['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($data['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('ip', 'like', '%'.$v.'%')->orWhere('path', 'like', '%'.$v.'%')))
            ->latest('id')->paginate(50)->withQueryString();
        $top = SecurityEvent::where('created_at', '>=', now()->subDay())->selectRaw('ip, count(*) as n')->groupBy('ip')->orderByDesc('n')->limit(10)->get();

        $path = CheckFileIntegrity::statusPath();
        $integrity = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return view('admin.security.threats', compact('events', 'top', 'integrity'));
    }

    private function filterActivity(Request $request, $query, string $relation, array $searchable)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return $query
            ->when($data['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('ip', 'like', '%'.$v.'%')
                ->orWhere('login_id', 'like', '%'.$v.'%')
                ->orWhere('path', 'like', '%'.$v.'%')
                ->orWhereHas($relation, fn ($r) => $r->where(fn ($rr) => collect($searchable)->each(fn ($col) => $rr->orWhere($col, 'like', '%'.$v.'%'))))))
            ->when($data['event'] ?? null, fn ($q, $v) => $q->where('event', 'like', $v.'%'))
            ->when($data['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v, 'Asia/Kolkata')->startOfDay()->utc()))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v, 'Asia/Kolkata')->endOfDay()->utc()))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();
    }
}
