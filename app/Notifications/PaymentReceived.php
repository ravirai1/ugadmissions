<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification
{
    public function __construct(private Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payment;
        $mail = (new MailMessage)
            ->subject("Payment received ({$p->receipt_no}) – St. Stephen's College UG Admissions 2027")
            ->greeting('Dear '.$notifiable->name.',')
            ->line("We have received your payment of **₹".number_format((float) $p->amount, 2)."** for {$p->purposeLabel()}.")
            ->line("Receipt no.: **{$p->receipt_no}** · Order no.: {$p->order_no} · SBI ePay ref.: ".($p->gateway_ref ?: '–'));

        if ($p->is_duplicate) {
            $mail->line('This payment is for something you had already paid for. The Accounts office will review it for refund.');
        }

        return $mail->action('View Receipt', route('payment.receipt', $p))
            ->line('You can also download the receipt from your dashboard at any time.');
    }
}
