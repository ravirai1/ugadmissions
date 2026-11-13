<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SBI ePay aggregator hosted integration.
 *
 * Field order follows SBI's aggregator integration document; confirm it against the kit SBI issues
 * to the College before going live (only the two arrays below need changing if it differs).
 */
class SbiEpayGateway implements PaymentGateway
{
    public function __construct(private array $cfg, private SbiEpayCrypto $crypto) {}

    public function requestForm(Payment $payment, string $returnUrl): array
    {
        $fields = [
            $this->cfg['merchant_id'],
            $this->cfg['operating_mode'],
            $this->cfg['country'],
            $this->cfg['currency'],
            number_format((float) $payment->amount, 2, '.', ''),
            'APP-'.$payment->application_id,       // other details
            $returnUrl,                             // success URL
            $returnUrl,                             // fail URL
            $this->cfg['aggregator_id'],
            $payment->order_no,                     // merchant order no
            'APP'.$payment->application_id,        // merchant customer id
            'NB',                                   // pay mode (gateway shows all enabled modes)
            'ONLINE',
            'ONLINE',
        ];

        return [
            'action' => $this->cfg['transaction_url'],
            'fields' => [
                'EncryptTrans' => $this->crypto->encrypt(implode('|', $fields)),
                'merchIdVal' => $this->cfg['merchant_id'],
            ],
        ];
    }

    public function parseResponse(Request $request): ?GatewayResult
    {
        $enc = (string) $request->input('encData', $request->input('pushRespData', ''));
        $plain = $enc !== '' ? $this->crypto->decrypt($enc) : null;
        if ($plain === null) {
            return null;
        }

        // MerchantOrderNo|SBIePayRefID|Status|Amount|Currency|PayMode|OtherDetails|Reason|BankCode|BankReferenceNo|TransactionDate|Country|CIN|MerchantID|...
        $p = explode('|', $plain);

        return new GatewayResult(
            orderNo: $p[0] ?? null,
            status: GatewayResult::mapStatus($p[2] ?? null),
            amount: isset($p[3]) ? (float) $p[3] : null,
            gatewayRef: $p[1] ?? null,
            bankRef: $p[9] ?? null,
            payMode: $p[5] ?? null,
            gatewayStatus: $p[2] ?? null,
            reason: $p[7] ?? null,
            merchantId: $p[13] ?? null,
        );
    }

    public function queryStatus(Payment $payment): ?GatewayResult
    {
        try {
            $res = Http::asForm()->timeout($this->cfg['timeout'])->post($this->cfg['status_url'], [
                'queryRequest' => '|'.$this->cfg['merchant_id'].'|'.$payment->order_no.'|'.number_format((float) $payment->amount, 2, '.', ''),
                'aggregatorId' => $this->cfg['aggregator_id'],
                'merchantId' => $this->cfg['merchant_id'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('SBI ePay status query failed', ['order' => $payment->order_no, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $res->successful() || trim($res->body()) === '') {
            return null;
        }

        // MerchantID|SBIePayRefID|Status|Country|Currency|OtherDetails|MerchantOrderNo|Amount|StatusDescription|BankCode|BankReferenceNo|TransactionDate|PayMode|...
        $p = explode('|', trim($res->body()));
        if (($p[6] ?? null) !== $payment->order_no) {
            return null;
        }

        return new GatewayResult(
            orderNo: $p[6],
            status: GatewayResult::mapStatus($p[2] ?? null),
            amount: isset($p[7]) ? (float) $p[7] : null,
            gatewayRef: $p[1] ?? null,
            bankRef: $p[10] ?? null,
            payMode: $p[12] ?? null,
            gatewayStatus: $p[2] ?? null,
            reason: $p[8] ?? null,
            merchantId: $p[0] ?? null,
        );
    }
}
