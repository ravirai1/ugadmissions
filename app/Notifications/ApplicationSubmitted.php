<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationSubmitted extends Notification
{
    public function __construct(private Application $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Application {$this->application->application_no} submitted – St. Stephen's College UG Admissions 2027")
            ->greeting('Dear '.$notifiable->name.',')
            ->line("Your application has been submitted. Your application number is **{$this->application->application_no}**.")
            ->line('Your application is complete only after the application fee is paid. Log in to your dashboard to pay.')
            ->action('Go to Dashboard', route('applicant.dashboard'));
    }
}
