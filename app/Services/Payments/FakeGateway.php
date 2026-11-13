<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Local test gateway: a page that lets a tester choose success / failure / no response,
 * so the full flow can be tried without SBI credentials. Disabled in production.
 */
class FakeGateway implements PaymentGateway
{
    public function requestForm(Payment $payment, string $returnUrl): array
    {
        return [
            'action' => route('payment.fake'),
            'fields' => ['order_no' => $payment->order_no, 'sig' => $this->sign($payment->order_no)],
        ];
    }

    public function parseResponse(Request $request): ?GatewayResult
    {
        $order = (string) $request->input('order_no');
        if (! hash_equals($this->sign($order), (string) $request->input('sig'))) {
            return null;
        }
        $payment = Payment::where('order_no', $order)->first();

        return $payment ? $this->result($payment, (string) $request->input('outcome')) : null;
    }

    public function queryStatus(Payment $payment): ?GatewayResult
    {
        // "Bank" outcome recorded by the fake page, even when the browser never came back.
        $outcome = Cache::get('fakepay:'.$payment->order_no);

        return $outcome ? $this->result($payment, $outcome === 'timeout' ? 'success' : $outcome) : new GatewayResult($payment->order_no, 'pending', gatewayStatus: 'PENDING');
    }

    public function sign(string $order): string
    {
        return hash_hmac('sha256', $order, config('app.key'));
    }

    private function result(Payment $payment, string $outcome): GatewayResult
    {
        $ok = $outcome === 'success';

        return new GatewayResult(
            orderNo: $payment->order_no,
            status: $ok ? 'success' : 'failed',
            amount: (float) $payment->amount,
            gatewayRef: 'FAKE'.substr(md5($payment->order_no), 0, 10),
            bankRef: 'BNK'.substr(md5('b'.$payment->order_no), 0, 8),
            payMode: 'NB',
            gatewayStatus: $ok ? 'SUCCESS' : 'FAIL',
            reason: $ok ? null : 'Declined in test gateway',
        );
    }
}
