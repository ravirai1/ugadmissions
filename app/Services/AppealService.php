<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Scrutiny;
use App\Models\ScrutinyAppeal;
use App\Notifications\AppealDecided;
use App\Notifications\AppealReceived;
use App\Support\Roles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/** Applicant appeals against a scrutiny rejection; the TFA admin (final authority) decides. */
class AppealService
{
    public function __construct(private DocumentStore $documents, private ScrutinyService $scrutiny) {}

    /** @param  UploadedFile[]  $files */
    public function submit(Scrutiny $s, string $reason, array $files, ?string $ip): ScrutinyAppeal
    {
        if (! $s->canAppeal()) {
            throw new RuntimeException('This decision can no longer be appealed.');
        }

        $appeal = DB::transaction(function () use ($s, $reason, $files, $ip) {
            $appeal = ScrutinyAppeal::create([
                'scrutiny_id' => $s->id,
                'application_id' => $s->application_id,
                'rejection_remarks' => $s->remarks,
                'reason' => $reason,
                'status' => ScrutinyAppeal::PENDING,
                'ip' => $ip,
            ]);
            foreach (array_values($files) as $i => $file) {
                $doc = $this->documents->store($s->application, $file, 'other', "files.$i", replace: false);
                $doc->forceFill(['appeal_id' => $appeal->id])->save();
            }

            return $appeal;
        });

        $tfa = Admin::where('role', Roles::TFA)->where('is_active', true)->get();
        if ($tfa->isNotEmpty()) {
            Notification::send($tfa, new AppealReceived($appeal));
        }

        return $appeal;
    }

    public function decide(ScrutinyAppeal $appeal, string $outcome, string $remarks, Admin $admin): ScrutinyAppeal
    {
        if (! $appeal->isPending()) {
            throw new RuntimeException('This appeal has already been decided.');
        }

        DB::transaction(function () use ($appeal, $outcome, $remarks, $admin) {
            $appeal->update(['status' => $outcome, 'decision_remarks' => $remarks, 'decided_by' => $admin->id, 'decided_at' => now()]);
            if ($outcome === ScrutinyAppeal::ALLOWED) {
                // Rejection set aside: the record goes back to the scrutiny queue, flagged so staff see the appeal.
                $this->scrutiny->reopen($appeal->scrutiny);
                $appeal->scrutiny->update(['reply_received' => true]);
            }
            AuditLog::record('scrutiny.appeal.'.$outcome, $appeal, ['scrutiny_id' => $appeal->scrutiny_id]);
        });

        $appeal->application->user->notify(new AppealDecided($appeal));

        return $appeal;
    }
}
