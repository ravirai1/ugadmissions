<?php

namespace App\Notifications;

use App\Models\SeatOffer;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SeatOffered extends Notification
{
    public function __construct(public SeatOffer $offer) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->offer;
        $deadline = $o->deadline_at->timezone('Asia/Kolkata')->format('d M Y, h:i A');

        return (new MailMessage)
            ->subject("Seat offered: {$o->course->name} – St. Stephen's College")
            ->greeting('Dear '.$notifiable->name.',')
            ->line("Congratulations. You have been offered a seat in **{$o->course->name}** (round {$o->round}).")
            ->line("Your score: CUET {$o->cuet_part}/85 + interview {$o->interview_part}/15 = **{$o->total}/100**.")
            ->line("Please accept the offer on your dashboard by **{$deadline} IST**. If you do not accept by then, the seat lapses and is offered to the next candidate.")
            ->action('View offer letter', route('applicant.offer', $o))
            ->line("Offer no.: {$o->offer_no}");
    }
}
