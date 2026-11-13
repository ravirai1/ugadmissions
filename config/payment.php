<?php

return [
    // Application fees (₹). Editable by the super admin from the panel in step 4.
    'fees' => [
        'first_course' => (int) env('FEE_FIRST_COURSE', 500),
        'additional_course' => (int) env('FEE_ADDITIONAL_COURSE', 200),
        'residence' => (int) env('FEE_RESIDENCE', 1000),
        'course_after_payment' => (int) env('FEE_COURSE_AFTER_PAYMENT', 500),
        'residence_after_payment' => (int) env('FEE_RESIDENCE_AFTER_PAYMENT', 1000),
    ],

    // Applicants can ask us to re-check an unconfirmed payment with SBI after this many minutes.
    'status_check_after_minutes' => (int) env('PAYMENT_STATUS_CHECK_AFTER', 45),

    // Automatic background re-check stops after this many hours.
    'reconcile_max_hours' => (int) env('PAYMENT_RECONCILE_MAX_HOURS', 72),

    'sbi_epay' => [
        // "sbi" for the real gateway, "fake" for local testing (never in production).
        'driver' => env('SBI_EPAY_DRIVER', 'fake'),

        'merchant_id' => env('SBI_EPAY_MERCHANT_ID', ''),
        'aggregator_id' => env('SBI_EPAY_AGGREGATOR_ID', 'SBIEPAY'),
        'key' => env('SBI_EPAY_KEY', ''),               // encryption key from SBI's merchant kit
        'cipher' => env('SBI_EPAY_CIPHER', 'aes-128-ecb'), // aes-128-ecb or aes-256-gcm, per SBI's kit
        'operating_mode' => env('SBI_EPAY_OPERATING_MODE', 'DOM'),
        'country' => 'IN',
        'currency' => 'INR',

        // Endpoints (test: https://test.sbiepay.sbi/...). Confirm against SBI's integration document.
        'transaction_url' => env('SBI_EPAY_TRANSACTION_URL', 'https://www.sbiepay.sbi/secure/AggregatorHostedListener'),
        'status_url' => env('SBI_EPAY_STATUS_URL', 'https://www.sbiepay.sbi/payagg/statusQuery/getStatusQuery'),
        'timeout' => 20,
    ],
];
