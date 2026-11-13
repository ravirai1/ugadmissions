<?php

namespace App\Notifications;

use App\Models\CallLetter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CallLetterIssued extends Notification
{
    public function __construct(public CallLetter $letter) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $l = $this->letter;

        return (new MailMessage)
            ->subject("Interview call letter: {$l->course->name} – St. Stephen's College")
            ->greeting('Dear '.$notifiable->name.',')
            ->line("You are called for the interview for **{$l->course->name}**.")
            ->line('Date: **'.$l->interview_date->format('l, d M Y')."**  \nReporting time: **{$l->reporting_time}**  \nVenue: {$l->venue}")
            ->line('Print your call letter (it has a QR code for the attendance desk) and bring it with your original documents and a photo ID.')
            ->action('Open call letter', route('applicant.call-letter', $l))
            ->line("Call letter no.: {$l->letter_no}");
    }
}
