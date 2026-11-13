<?php

namespace App\Notifications;

use App\Models\ScrutinyQuery;
use App\Support\DocumentTypes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QueryRaised extends Notification
{
    public function __construct(protected ScrutinyQuery $query, protected string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function subjectLine(): string
    {
        return 'Action needed on your application '.$this->query->application->application_no." – St. Stephen's College";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $q = $this->query;
        $mail = (new MailMessage)
            ->subject($this->subjectLine())
            ->greeting('Dear '.$notifiable->name.',')
            ->line('The '.($q->scrutiny->type === 'religious' ? 'religious' : 'academic').' scrutiny team has a query about your application:')
            ->line('"'.$q->message.'"');

        if ($q->document_types) {
            $mail->line('Please upload: '.collect($q->document_types)->map(fn ($t) => DocumentTypes::label($t))->implode(', ').'.');
        }

        return $mail->action('Reply and upload documents', route('query.show', [$q, $this->token]))
            ->line('This secure link works until '.$q->expires_at->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST. You can also reply from your dashboard after logging in.')
            ->line('Do not forward this email: the link lets anyone add documents to your application.');
    }
}
