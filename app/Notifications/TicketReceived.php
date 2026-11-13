<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReceived extends Notification
{
    public function __construct(private Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("We received your query ({$this->ticket->ticket_no}) – St. Stephen's College UG Admissions")
            ->greeting('Dear '.$this->ticket->name.',')
            ->line("Thank you for writing to the Admissions Helpdesk. Your query number is **{$this->ticket->ticket_no}**.")
            ->line('We will reply to this email address. Please do not send the same query again.');
    }
}
