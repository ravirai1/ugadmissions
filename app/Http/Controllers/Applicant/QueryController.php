<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ScrutinyQuery;
use App\Services\DocumentStore;
use App\Services\ScrutinyService;
use App\Support\DocumentTypes;
use Illuminate\Http\Request;

/**
 * Applicant replies to a scrutiny query, either from the emailed secure link (no login) or from the dashboard.
 */
class QueryController extends Controller
{
    public function __construct(private ScrutinyService $service) {}

    public function showByLink(ScrutinyQuery $query, string $token)
    {
        abort_unless($query->linkValid($token), 403, 'This link has expired or the query has already been answered. Log in to your dashboard to see your queries.');

        return $this->page($query, route('query.reply', [$query, $token]));
    }

    public function replyByLink(Request $request, ScrutinyQuery $query, string $token)
    {
        abort_unless($query->linkValid($token), 403, 'This link has expired or the query has already been answered.');

        return $this->handle($request, $query);
    }

    public function show(Request $request, ScrutinyQuery $query)
    {
        $this->own($request, $query);

        return $this->page($query, $query->isOpen() ? route('applicant.query.reply', $query) : null);
    }

    public function reply(Request $request, ScrutinyQuery $query)
    {
        $this->own($request, $query);
        abort_unless($query->isOpen(), 403, 'This query is no longer open.');

        return $this->handle($request, $query);
    }

    private function own(Request $request, ScrutinyQuery $query): void
    {
        abort_unless($query->application->user_id === $request->user()->id, 404);
    }

    private function page(ScrutinyQuery $query, ?string $action)
    {
        return view('applicant.query', ['q' => $query->load('application.user', 'attachments'), 'action' => $action]);
    }

    private function handle(Request $request, ScrutinyQuery $query)
    {
        $types = $query->document_types ?? [];
        $rules = ['reply' => [$types ? 'nullable' : 'required', 'string', 'max:2000'], 'extra' => ['nullable', 'array', 'max:3']];
        $messages = [];
        foreach ($types as $t) {
            $rules["files.$t"] = DocumentStore::rules($t);
            $messages += DocumentStore::messages("files.$t", $t) + ["files.$t.required" => 'Please upload: '.DocumentTypes::label($t).'.'];
        }
        $rules['extra.*'] = DocumentStore::rules('other', false);
        $messages += DocumentStore::messages('extra.*', 'other');
        $request->validate($rules, $messages);

        $this->service->reply($query, $request->input('reply'), array_intersect_key($request->file('files', []), array_flip($types)), $request->file('extra', []), $request->ip());

        return redirect($request->user() ? route('applicant.dashboard') : route('home'))
            ->with('status', 'Thank you. Your reply and documents have been added to application '.$query->application->application_no.'.');
    }
}
