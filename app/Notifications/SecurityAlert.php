<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAlert extends Notification
{
    /** @param  array<string, string>  $details */
    public function __construct(public string $title, public array $details, public ?string $link = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->error()
            ->subject('[Security alert] '.$this->title.' – UG Admissions 2027')
            ->line('**'.$this->title.'**');
        foreach ($this->details as $k => $v) {
            $mail->line($k.': '.$v);
        }
        if ($this->link) {
            $mail->action('Open in staff panel', $this->link);
        }

        return $mail->line('Time: '.now('Asia/Kolkata')->format('d M Y, h:i:s A').' IST')
            ->line('This is an automatic email from the admissions portal.');
    }
}
