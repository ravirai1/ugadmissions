<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use Illuminate\Http\Request;

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
            'guard' => ['required', 'in:admin,applicant'],
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

        return back()->with('status', "IP {$data['ip']} blocked.");
    }

    public function unblock(BlockedIp $blockedIp)
    {
        AuditLog::record('security.ip_unblocked', null, ['ip' => $blockedIp->ip, 'guard' => $blockedIp->guard]);
        $blockedIp->delete();

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
}
