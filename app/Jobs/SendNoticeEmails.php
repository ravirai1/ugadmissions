<?php

namespace App\Jobs;

use App\Models\Notice;
use App\Notifications\NoticePublished;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/** Emails a notice to its audience. Each email is its own queued notification, so one bad address cannot stop the rest. */
class SendNoticeEmails implements ShouldQueue
{
    use Queueable;

    public function __construct(public Notice $notice) {}

    public function handle(): void
    {
        $count = 0;
        $this->notice->recipients()->select('id', 'name', 'email')->chunkById(500, function ($users) use (&$count) {
            Notification::send($users, new NoticePublished($this->notice));
            $count += $users->count();
        });
        $this->notice->forceFill(['emailed_count' => $count])->save();
    }
}
