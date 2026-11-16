<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrutinyAppeal;
use App\Services\AppealService;
use App\Support\Roles;
use Illuminate\Http\Request;
use RuntimeException;

/** Appeals against scrutiny rejections. Admins can see them; only the TFA admin decides. */
class AppealController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->validate(['status' => ['nullable', 'in:all,'.implode(',', array_keys(ScrutinyAppeal::STATUSES))]])['status'] ?? ScrutinyAppeal::PENDING;

        return view('admin.appeals.index', [
            'appeals' => ScrutinyAppeal::with('application.user', 'scrutiny.applicationCourse.course', 'decidedBy')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(25)->withQueryString(),
            'counts' => ScrutinyAppeal::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'status' => $status,
        ]);
    }

    public function show(Request $request, ScrutinyAppeal $appeal)
    {
        return view('admin.appeals.show', [
            'a' => $appeal->load('application.user', 'scrutiny.applicationCourse.course', 'scrutiny.reviewer', 'scrutiny.reviewFlaggedBy', 'attachments', 'decidedBy'),
            'canDecide' => $appeal->isPending() && $request->user('admin')->hasRole(Roles::TFA),
        ]);
    }

    public function decide(Request $request, ScrutinyAppeal $appeal, AppealService $appeals)
    {
        abort_unless($request->user('admin')->hasRole(Roles::TFA), 403, 'Only the TFA admin can decide appeals.');
        $data = $request->validate([
            'outcome' => ['required', 'in:'.ScrutinyAppeal::UPHELD.','.ScrutinyAppeal::ALLOWED],
            'remarks' => ['required', 'string', 'min:10', 'max:2000'],
        ], ['remarks.required' => 'Write the remarks the applicant will see.']);

        try {
            $appeals->decide($appeal, $data['outcome'], $data['remarks'], $request->user('admin'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['outcome' => $e->getMessage()]);
        }

        return redirect()->route('admin.appeals.index')->with('status', $data['outcome'] === ScrutinyAppeal::ALLOWED
            ? 'Appeal accepted. The record is back in the scrutiny queue.'
            : 'Appeal rejected. The rejection stands.');
    }
}
