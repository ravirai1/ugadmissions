<?php

namespace App\Notifications;

use App\Models\ScrutinyAppeal;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppealDecided extends Notification
{
    public function __construct(public ScrutinyAppeal $appeal) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->appeal;
        $mail = (new MailMessage)
            ->subject('Decision on your appeal – '.$a->application->application_no." – St. Stephen's College")
            ->greeting('Dear '.$notifiable->name.',');

        if ($a->status === ScrutinyAppeal::ALLOWED) {
            $mail->line('Your appeal has been accepted. The earlier rejection is set aside and your application will be scrutinised again. You will see the new result on your dashboard.');
        } else {
            $mail->line('After review, your appeal has not been accepted and the earlier decision stands. This decision is final.');
        }

        return $mail->line('Remarks: "'.$a->decision_remarks.'"')
            ->action('Open your dashboard', route('applicant.dashboard'));
    }
}
