<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--limit=200 : Most payments to check in one run}';

    protected $description = 'Ask SBI ePay for the status of unconfirmed payments and generate receipts for any that succeeded';

    public function handle(PaymentService $payments): int
    {
        $wait = (int) config('payment.status_check_after_minutes');
        $list = Payment::whereIn('status', [Payment::INITIATED, Payment::PENDING])
            ->where('created_at', '<=', now()->subMinutes($wait))
            ->where('created_at', '>=', now()->subHours((int) config('payment.reconcile_max_hours')))
            ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subMinutes(14)))
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $confirmed = 0;
        foreach ($list as $payment) {
            if ($payments->check($payment, 'reconcile')->isSuccess()) {
                $confirmed++;
            }
        }

        $this->info("Checked {$list->count()} payment(s); {$confirmed} confirmed as paid.");

        return self::SUCCESS;
    }
}
