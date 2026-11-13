<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * Encryption used by SBI ePay. SBI ships the exact algorithm in its merchant kit;
 * both variants seen in kits are supported, selected by SBI_EPAY_CIPHER.
 */
class SbiEpayCrypto
{
    public function __construct(private string $key, private string $cipher = 'aes-128-ecb') {}

    public function encrypt(string $plain): string
    {
        $this->guard();

        if ($this->cipher === 'aes-256-gcm') {
            $iv = substr($this->key, 0, 12);
            $out = openssl_encrypt($plain, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);

            return base64_encode($out.$tag);
        }

        return base64_encode(openssl_encrypt($plain, 'aes-128-ecb', $this->key, OPENSSL_RAW_DATA));
    }

    public function decrypt(string $encoded): ?string
    {
        $this->guard();
        $raw = base64_decode(strtr(trim($encoded), ' ', '+'), true);
        if ($raw === false) {
            return null;
        }

        if ($this->cipher === 'aes-256-gcm') {
            if (strlen($raw) < 17) {
                return null;
            }
            $iv = substr($this->key, 0, 12);
            $plain = openssl_decrypt(substr($raw, 0, -16), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, substr($raw, -16));
        } else {
            $plain = openssl_decrypt($raw, 'aes-128-ecb', $this->key, OPENSSL_RAW_DATA);
        }

        return $plain === false ? null : $plain;
    }

    private function guard(): void
    {
        if ($this->key === '') {
            throw new RuntimeException('SBI ePay key is not configured (SBI_EPAY_KEY).');
        }
    }
}
