<?php

namespace App\Notifications;

use App\Models\Notice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NoticePublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Notice $notice) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->notice->title." – St. Stephen's College UG Admissions")
            ->greeting('Dear '.$notifiable->name.',');
        foreach (preg_split("/\R{2,}/", trim($this->notice->body)) as $para) {
            $mail->line($para);
        }

        return $mail->action('Open your dashboard', route('applicant.dashboard'))
            ->line('This is an official notice from the UG Admissions office. Please do not reply to this email; use the Help page on the admissions website instead.');
    }
}
