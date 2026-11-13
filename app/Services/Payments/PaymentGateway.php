<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /** @return array{action: string, fields: array<string,string>} form to auto-post to the gateway */
    public function requestForm(Payment $payment, string $returnUrl): array;

    /** Decrypts and parses what the gateway posted back. Returns null if it cannot be trusted. */
    public function parseResponse(Request $request): ?GatewayResult;

    /** Asks the gateway for the current status of a transaction (double verification). */
    public function queryStatus(Payment $payment): ?GatewayResult;
}
