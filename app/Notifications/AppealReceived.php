<?php

namespace App\Notifications;

use App\Models\ScrutinyAppeal;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppealReceived extends Notification
{
    public function __construct(public ScrutinyAppeal $appeal) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->appeal;
        $what = $a->scrutiny->type === 'religious' ? 'religious (Christian category)' : 'academic ('.$a->scrutiny->applicationCourse?->course->name.')';

        return (new MailMessage)
            ->subject('New appeal – '.$a->application->application_no.' – UG Admissions 2027')
            ->line('An applicant has appealed against a '.$what.' scrutiny rejection.')
            ->line('Application: '.$a->application->application_no.' ('.$a->application->user->name.')')
            ->action('Review the appeal', route('admin.appeals.show', $a))
            ->line('Only the TFA admin can decide appeals.');
    }
}
