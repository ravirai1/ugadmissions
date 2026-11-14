<?php

namespace App\Services\Payments;

use App\Models\Admin;
use App\Models\Application;
use App\Models\Payment;
use App\Notifications\PaymentReceived;
use App\Services\FeeCalculator;
use App\Services\ScrutinyService;
use App\Services\SecurityAlerts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Creates payment attempts, applies what the gateway reports and gives the applicant what they paid for.
 *
 * Amounts are always worked out here on the server. A "success" from the browser redirect or push is
 * double-checked with the gateway's status API before it is accepted, and a payment already marked
 * successful is never changed again (replays are ignored).
 */
class PaymentService
{
    public function __construct(private PaymentGateway $gateway, private FeeCalculator $fees) {}

    public function gateway(): PaymentGateway
    {
        return $this->gateway;
    }

    public function initiateApplicationFee(Application $app, ?string $ip): Payment
    {
        if (! $app->isSubmitted()) {
            throw new RuntimeException('Submit your application before paying.');
        }
        if ($app->isPaid()) {
            throw new RuntimeException('The application fee has already been paid.');
        }
        $fee = $this->fees->forApplication($app);
        $items = array_map(fn ($l) => $l + ['type' => 'line'], $fee['lines']);

        return $this->create($app, Payment::APPLICATION, $items, $fee['total'], $ip);
    }

    /**
     * @param  array<int, array{label: string, row: array<string,mixed>}>  $courses  validated course rows to add
     */
    public function initiateAddCourses(Application $app, array $courses, ?string $ip): Payment
    {
        $this->assertPaid($app);
        $each = FeeCalculator::fees()['course_after_payment'];
        $items = array_map(fn ($c) => ['type' => 'course', 'label' => $c['label'], 'amount' => $each, 'row' => $c['row']], $courses);

        return $this->create($app, Payment::ADD_COURSE, $items, $this->fees->forAddedCourses(count($items)), $ip);
    }

    public function initiateAddResidence(Application $app, ?string $ip): Payment
    {
        $this->assertPaid($app);
        if ($app->residence) {
            throw new RuntimeException('You have already applied for residence.');
        }
        $amount = $this->fees->forAddedResidence();

        return $this->create($app, Payment::ADD_RESIDENCE, [['type' => 'residence', 'label' => 'Residence', 'amount' => $amount]], $amount, $ip);
    }

    /** Ask the gateway for the latest status and apply it. */
    public function check(Payment $payment, string $source, ?Admin $admin = null, ?string $ip = null): Payment
    {
        $result = $this->gateway->queryStatus($payment);

        $payment->forceFill(['last_checked_at' => now(), 'check_count' => $payment->check_count + 1])->save();

        if (! $result) {
            $this->event($payment, $source, null, 'Gateway did not return a usable status.', $admin, $ip);

            return $payment;
        }

        return $this->apply($payment, $result, $source, $admin, $ip, verified: true);
    }

    /**
     * Apply a gateway result. $verified is true when the result itself came from the status API.
     */
    public function apply(Payment $payment, GatewayResult $result, string $source, ?Admin $admin = null, ?string $ip = null, bool $verified = false): Payment
    {
        if ($result->orderNo !== $payment->order_no) {
            $this->event($payment, $source, null, 'Ignored: order number mismatch ('.$result->orderNo.').', $admin, $ip);

            return $payment;
        }

        $status = $result->status;
        $note = $result->summary();

        if ($status === Payment::SUCCESS && ! $this->amountMatches($payment, $result)) {
            $status = Payment::FAILED;
            $note = 'Amount mismatch: expected '.$payment->amount.', gateway reported '.$result->amount.'. '.$note;
            Log::warning('Payment amount mismatch', ['order' => $payment->order_no, 'expected' => $payment->amount, 'got' => $result->amount]);
            $this->alert($payment, 'Payment amount mismatch', ['Expected' => '₹'.$payment->amount, 'SBI reported' => '₹'.$result->amount]);
        }
        if ($status === Payment::SUCCESS && $result->merchantId && config('payment.sbi_epay.merchant_id') && $result->merchantId !== config('payment.sbi_epay.merchant_id')) {
            $status = Payment::FAILED;
            $note = 'Merchant id mismatch. '.$note;
            $this->alert($payment, 'Payment merchant id mismatch', ['SBI reported merchant' => (string) $result->merchantId]);
        }

        // Double verification: a success from the browser or push must be confirmed by the status API.
        if ($status === Payment::SUCCESS && ! $verified) {
            $confirm = $this->gateway->queryStatus($payment);
            if (! $confirm || $confirm->status !== Payment::SUCCESS || ! $this->amountMatches($payment, $confirm)) {
                $this->event($payment, $source, Payment::PENDING, 'Success reported but not yet confirmed by status API. '.$note, $admin, $ip);
                $this->setStatus($payment, Payment::PENDING, $result);

                return $payment->refresh();
            }
            $result = $confirm;
        }

        $fulfilled = false;
        DB::transaction(function () use ($payment, $result, $status, $source, $admin, $ip, $note, &$fulfilled) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            $this->event($locked, $source, $status, $note, $admin, $ip);

            if ($locked->isSuccess()) {
                return; // already final; replays and late failures change nothing
            }
            if ($status !== Payment::SUCCESS) {
                $this->setStatus($locked, $status, $result);

                return;
            }

            $locked->forceFill([
                'status' => Payment::SUCCESS,
                'paid_at' => now(),
                'gateway_ref' => $result->gatewayRef ?? $locked->gateway_ref,
                'bank_ref' => $result->bankRef ?? $locked->bank_ref,
                'pay_mode' => $result->payMode ?? $locked->pay_mode,
                'gateway_status' => $result->gatewayStatus,
                'gateway_reason' => $result->reason,
            ])->save();
            $locked->forceFill(['receipt_no' => 'SSC27R'.str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT)])->save();

            $this->fulfil($locked);
            $fulfilled = true;
        });

        $payment->refresh();
        if ($fulfilled) {
            app(ScrutinyService::class)->sync($payment->application); // now in the departments' scrutiny queues
            $payment->application->user->notify(new PaymentReceived($payment));
        }

        return $payment;
    }

    /** Give the applicant what the payment was for. A second payment for the same thing is flagged as a duplicate for refund. */
    private function fulfil(Payment $payment): void
    {
        $app = Application::whereKey($payment->application_id)->lockForUpdate()->first();

        switch ($payment->purpose) {
            case Payment::APPLICATION:
                if ($app->isPaid()) {
                    $payment->forceFill(['is_duplicate' => true])->save();
                    $this->alert($payment, 'Duplicate payment received (refund review needed)', []);
                    break;
                }
                $app->forceFill(['fee_paid_at' => now()])->save();
                $app->courses()->where('added_after_payment', false)->update(['payment_status' => 'paid']);
                break;

            case Payment::ADD_COURSE:
                $added = 0;
                foreach ($payment->items as $item) {
                    $row = $item['row'];
                    $existing = $app->courses()->where('course_id', $row['course_id'])->first();
                    if ($existing && $existing->payment_status === 'paid') {
                        continue;
                    }
                    $app->courses()->updateOrCreate(['course_id' => $row['course_id']], $row + ['added_after_payment' => true, 'payment_status' => 'paid']);
                    $added++;
                }
                if ($added === 0) {
                    $payment->forceFill(['is_duplicate' => true])->save();
                    $this->alert($payment, 'Duplicate payment received (refund review needed)', []);
                }
                break;

            case Payment::ADD_RESIDENCE:
                if ($app->residence) {
                    $payment->forceFill(['is_duplicate' => true])->save();
                    $this->alert($payment, 'Duplicate payment received (refund review needed)', []);
                    break;
                }
                $app->forceFill(['residence' => true, 'residence_added_after_payment' => true])->save();
                break;
        }
    }

    private function create(Application $app, string $purpose, array $items, int|float $amount, ?string $ip): Payment
    {
        if ($amount <= 0 || ! $items) {
            throw new RuntimeException('Nothing to pay for.');
        }

        do {
            $order = 'SSC'.now()->format('ymdHis').random_int(1000, 9999);
        } while (Payment::where('order_no', $order)->exists());

        $payment = $app->payments()->create([
            'order_no' => $order,
            'purpose' => $purpose,
            'items' => array_values($items),
            'amount' => $amount,
            'status' => Payment::INITIATED,
            'initiated_ip' => $ip,
        ]);
        $this->event($payment, 'initiate', Payment::INITIATED, 'Amount '.$amount, null, $ip);

        return $payment;
    }

    private function setStatus(Payment $payment, string $status, GatewayResult $r): void
    {
        if ($payment->isSuccess()) {
            return;
        }
        $payment->forceFill([
            'status' => $status,
            'gateway_ref' => $r->gatewayRef ?? $payment->gateway_ref,
            'bank_ref' => $r->bankRef ?? $payment->bank_ref,
            'pay_mode' => $r->payMode ?? $payment->pay_mode,
            'gateway_status' => $r->gatewayStatus,
            'gateway_reason' => $r->reason,
        ])->save();
    }

    private function amountMatches(Payment $payment, GatewayResult $r): bool
    {
        return $r->amount !== null && abs((float) $payment->amount - $r->amount) < 0.01;
    }

    private function assertPaid(Application $app): void
    {
        if (! $app->isPaid()) {
            throw new RuntimeException('Pay the application fee first.');
        }
    }

    private function event(Payment $payment, string $source, ?string $status, ?string $detail, ?Admin $admin, ?string $ip): void
    {
        $payment->events()->create([
            'source' => $source,
            'status' => $status,
            'detail' => $detail ? mb_substr($detail, 0, 1000) : null,
            'admin_id' => $admin?->id,
            'ip' => $ip,
        ]);
    }

    private function alert(Payment $payment, string $title, array $details): void
    {
        app(SecurityAlerts::class)->send('payment:'.$payment->id.':'.$title, $title, [
            'Order no.' => $payment->order_no,
            'Application' => (string) $payment->application?->application_no,
            'Purpose' => $payment->purposeLabel(),
        ] + $details, route('admin.payments.index', ['q' => $payment->order_no]));
    }
}
