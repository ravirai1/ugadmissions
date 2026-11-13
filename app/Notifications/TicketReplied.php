<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReplied extends Notification
{
    public function __construct(private Ticket $ticket, private TicketMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Reply to your query ({$this->ticket->ticket_no}) – St. Stephen's College UG Admissions")
            ->greeting('Dear '.$this->ticket->name.',')
            ->line('The Admissions Helpdesk has replied:')
            ->line($this->message->body);

        if ($this->ticket->channel === 'chat') {
            $mail->action('Open chat', route('help.chat'));
        } else {
            $mail->line('If you need more help, send a new query from the Contact page and mention '.$this->ticket->ticket_no.'.');
        }

        return $mail;
    }
}
