<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminOtp extends Notification
{
    public function __construct(private string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your staff login code – UG Admissions 2027')
            ->line("Your one-time login code is: **{$this->code}**")
            ->line('It expires in '.config('admissions.otp.expires_minutes').' minutes. Never share this code with anyone.')
            ->line('If you did not try to log in, contact the super admin immediately.');
    }
}
