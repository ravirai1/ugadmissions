<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Scrutiny;
use App\Models\ScrutinyQuery;
use App\Notifications\QueryRaised;
use App\Notifications\QueryReminder;
use App\Support\DocumentTypes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScrutinyService
{
    public const QUERY_LINK_DAYS = 7;

    public function __construct(private DocumentStore $documents) {}

    /**
     * Creates the scrutiny records a paid application needs: one academic record per paid course choice
     * (for that course's department) and one religious record for Christian categories. Safe to call repeatedly.
     */
    public function sync(Application $app): void
    {
        if (! $app->isPaid()) {
            return;
        }
        foreach ($app->courses()->where('payment_status', 'paid')->get() as $choice) {
            Scrutiny::firstOrCreate(['application_id' => $app->id, 'type' => Scrutiny::ACADEMIC, 'application_course_id' => $choice->id]);
        }
        if ($app->isChristian()) {
            Scrutiny::firstOrCreate(['application_id' => $app->id, 'type' => Scrutiny::RELIGIOUS, 'application_course_id' => null]);
        }
    }

    /** @param  array{status: string, remarks?: ?string, checklist?: array, verified_cuet_score?: ?float}  $data */
    public function decide(Scrutiny $s, array $data, Admin $admin): Scrutiny
    {
        $before = $s->status;
        $s->fill([
            'status' => $data['status'],
            'remarks' => $data['remarks'] ?? null,
            'checklist' => $data['checklist'] ?? [],
            'verified_cuet_score' => $s->type === Scrutiny::ACADEMIC ? ($data['verified_cuet_score'] ?? null) : null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'reply_received' => false,
        ])->save();

        AuditLog::record("scrutiny.{$s->type}.decide", $s, ['from' => $before, 'to' => $s->status]);

        return $s;
    }

    /** Puts the record back in the queue (admin only). */
    public function reopen(Scrutiny $s): void
    {
        $s->update(['status' => Scrutiny::PENDING, 'reviewed_at' => null]);
        AuditLog::record("scrutiny.{$s->type}.reopen", $s);
    }

    /**
     * Raise a query. Returns the query and the plain token for the secure upload link (only its hash is stored).
     *
     * @return array{0: ScrutinyQuery, 1: string}
     */
    public function raiseQuery(Scrutiny $s, string $message, array $documentTypes, Admin $admin): array
    {
        $token = Str::random(48);

        $query = DB::transaction(function () use ($s, $message, $documentTypes, $admin, $token) {
            $q = ScrutinyQuery::create([
                'application_id' => $s->application_id,
                'scrutiny_id' => $s->id,
                'raised_by' => $admin->id,
                'message' => $message,
                'document_types' => array_values(array_intersect($documentTypes, array_keys(DocumentTypes::TYPES))),
                'status' => ScrutinyQuery::OPEN,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(self::QUERY_LINK_DAYS),
            ]);
            $s->update(['status' => Scrutiny::QUERY, 'reply_received' => false, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);

            return $q;
        });

        $s->application->user->notify(new QueryRaised($query, $token));
        AuditLog::record("scrutiny.{$s->type}.query", $s, ['query_id' => $query->id]);

        return [$query, $token];
    }

    /**
     * Applicant's reply: text plus files. Files for a requested document type become the current version
     * of that document on the profile; extra files are attached as "other".
     *
     * @param  array<string, UploadedFile>  $files  document type => file
     * @param  UploadedFile[]  $extra
     */
    public function reply(ScrutinyQuery $q, ?string $text, array $files, array $extra, ?string $ip): void
    {
        DB::transaction(function () use ($q, $text, $files, $extra, $ip) {
            $app = $q->application;
            foreach ($files as $type => $file) {
                $this->documents->store($app, $file, $type, "files.$type", replace: true, queryId: $q->id);
            }
            foreach ($extra as $i => $file) {
                $this->documents->store($app, $file, 'other', "extra.$i", replace: false, queryId: $q->id);
            }
            $q->update(['status' => ScrutinyQuery::ANSWERED, 'reply' => $text, 'replied_at' => now(), 'reply_ip' => $ip, 'token_hash' => null]);

            // Back into the reviewer's queue, flagged.
            $q->scrutiny->update(['status' => Scrutiny::PENDING, 'reply_received' => true]);
        });
    }

    public function close(ScrutinyQuery $q, Admin $admin): void
    {
        $q->update(['status' => ScrutinyQuery::CLOSED, 'token_hash' => null, 'closed_by' => $admin->id, 'closed_at' => now()]);
        if ($q->scrutiny->status === Scrutiny::QUERY && ! $q->scrutiny->queries()->where('status', ScrutinyQuery::OPEN)->exists()) {
            $q->scrutiny->update(['status' => Scrutiny::PENDING]);
        }
        AuditLog::record('scrutiny.query.close', $q);
    }

    /** Daily reminder for open queries older than 2 days (at most 3 reminders); a fresh link is sent each time. */
    public function remind(): int
    {
        $sent = 0;
        $list = ScrutinyQuery::where('status', ScrutinyQuery::OPEN)
            ->where('created_at', '<=', now()->subDays(2))
            ->where('reminder_count', '<', 3)
            ->where(fn ($w) => $w->whereNull('reminded_at')->orWhere('reminded_at', '<=', now()->subHours(23)))
            ->with('application.user')->get();

        foreach ($list as $q) {
            $token = Str::random(48);
            $q->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(self::QUERY_LINK_DAYS), 'reminded_at' => now(), 'reminder_count' => $q->reminder_count + 1]);
            $q->application->user->notify(new QueryReminder($q, $token));
            $sent++;
        }

        return $sent;
    }
}
