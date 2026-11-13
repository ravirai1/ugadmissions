<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class ApplicantVerifyEmail extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your email – St. Stephen\'s College UG Admissions 2027')
            ->greeting('Dear Applicant,')
            ->line('Thank you for registering for UG Admissions 2027 at St. Stephen\'s College.')
            ->line('Please click the button below to verify your email address. The link is valid for 60 minutes.')
            ->action('Verify Email Address', $url)
            ->line('If you did not create this account, please ignore this email.');
    }
}
