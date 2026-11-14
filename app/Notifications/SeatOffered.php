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

        $mail = (new MailMessage)
            ->subject(($o->upgraded_from_id ? 'Seat moved up' : 'Seat offered').": {$o->course->name} – St. Stephen's College")
            ->greeting('Dear '.$notifiable->name.',');
        if ($o->upgraded_from_id) {
            $mail->line("Good news. A seat has opened in **{$o->course->name}**, which you placed higher in your DU preferences, so your seat has been moved up from {$o->upgradedFrom->course->name} (round {$o->round}). Your earlier seat has been released.");
        } else {
            $mail->line("Congratulations. You have been offered a seat in **{$o->course->name}** (round {$o->round}).");
        }
        $mail->line("Your score: CUET {$o->cuet_part}/85 + interview {$o->interview_part}/15 = **{$o->total}/100**.");
        $mail = $o->status === SeatOffer::ACCEPTED
            ? $mail->line('You had already accepted your earlier seat, so this seat is confirmed. You do not need to accept again.')
            : $mail->line("Please accept the offer on your dashboard by **{$deadline} IST**. If you do not accept by then, the seat lapses and is offered to the next candidate.");

        return $mail
            ->action('View offer letter', route('applicant.offer', $o))
            ->line("Offer no.: {$o->offer_no}");
    }
}
