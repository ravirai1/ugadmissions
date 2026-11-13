<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNoticeEmails;
use App\Models\AuditLog;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoticeController extends Controller
{
    public function index()
    {
        return view('admin.notices.index', ['notices' => Notice::with('author')->orderByDesc('is_pinned')->latest('id')->paginate(30)]);
    }

    public function create()
    {
        return view('admin.notices.form', ['notice' => new Notice(['audience' => 'public', 'is_published' => true])]);
    }

    public function store(Request $request)
    {
        $notice = new Notice($this->validated($request) + ['created_by' => $request->user('admin')->id]);
        $notice->published_at = $notice->is_published ? now() : null;
        $notice->save();
        AuditLog::record('notice.create', $notice);

        return redirect()->route('admin.notices.index')->with('status', 'Notice saved.');
    }

    public function edit(Notice $notice)
    {
        return view('admin.notices.form', ['notice' => $notice]);
    }

    public function update(Request $request, Notice $notice)
    {
        $notice->fill($this->validated($request));
        if ($notice->is_published && ! $notice->published_at) {
            $notice->published_at = now();
        }
        $notice->save();
        AuditLog::record('notice.update', $notice);

        return redirect()->route('admin.notices.index')->with('status', 'Notice updated.');
    }

    public function destroy(Notice $notice)
    {
        AuditLog::record('notice.delete', $notice, ['title' => $notice->title]);
        $notice->delete();

        return redirect()->route('admin.notices.index')->with('status', 'Notice deleted.');
    }

    /** Queue the notice for email to its audience. Each notice can be emailed once. */
    public function email(Notice $notice)
    {
        if ($notice->emailed_at) {
            return back()->withErrors(['email' => 'This notice has already been emailed.']);
        }
        if (! $notice->is_published) {
            return back()->withErrors(['email' => 'Publish the notice before emailing it.']);
        }
        $count = $notice->recipients()->count();
        $notice->forceFill(['emailed_at' => now(), 'emailed_count' => $count])->save();
        SendNoticeEmails::dispatch($notice);
        AuditLog::record('notice.email', $notice, ['recipients' => $count]);

        return back()->with('status', "Email queued for {$count} applicants. It goes out in the background over the next few minutes.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::in(array_keys(Notice::AUDIENCES))],
        ]);

        return $data + ['is_published' => $request->boolean('is_published'), 'is_pinned' => $request->boolean('is_pinned')];
    }
}
