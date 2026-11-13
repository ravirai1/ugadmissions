<?php

namespace App\Services\Payments;

final class GatewayResult
{
    public function __construct(
        public readonly ?string $orderNo,
        public readonly string $status,       // success | failed | pending
        public readonly ?float $amount = null,
        public readonly ?string $gatewayRef = null,
        public readonly ?string $bankRef = null,
        public readonly ?string $payMode = null,
        public readonly ?string $gatewayStatus = null,
        public readonly ?string $reason = null,
        public readonly ?string $merchantId = null,
    ) {}

    public static function mapStatus(?string $s): string
    {
        return match (strtoupper(trim((string) $s))) {
            'SUCCESS', 'SUCCESSFUL' => 'success',
            'PENDING', 'BOOKED', 'INITIATED' => 'pending',
            default => 'failed',
        };
    }

    public function summary(): string
    {
        return trim("{$this->gatewayStatus} ref={$this->gatewayRef} bank={$this->bankRef} amount={$this->amount} {$this->reason}");
    }
}
