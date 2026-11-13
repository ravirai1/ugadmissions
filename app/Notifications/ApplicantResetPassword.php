<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ApplicantResetPassword extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your password – St. Stephen\'s College UG Admissions 2027')
            ->line('We received a request to reset the password for your admissions account.')
            ->action('Reset Password', $url)
            ->line('This link expires in '.config('auth.passwords.users.expire').' minutes.')
            ->line('If you did not request a reset, no action is needed.');
    }
}
