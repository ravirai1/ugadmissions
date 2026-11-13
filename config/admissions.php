<?php

return [
    'session_name' => 'UG Admissions 2027',

    // CUET application number must start with this prefix (CUET 2027 numbers start with "27").
    'cuet_prefix' => env('CUET_PREFIX', '27'),
    'cuet_regex' => env('CUET_REGEX', '/^27[0-9]{8,13}$/'),

    // Non-obvious URL prefix for the staff login and panel.
    'admin_path' => env('ADMIN_PATH', 'ssc-staff'),

    // Failed-login IP blocking.
    'lockout' => [
        'admin' => [
            'max_attempts' => (int) env('ADMIN_MAX_ATTEMPTS', 5),
            'window_minutes' => (int) env('ADMIN_ATTEMPT_WINDOW', 15),
            'block_minutes' => (int) env('ADMIN_BLOCK_MINUTES', 30),
        ],
        'applicant' => [
            'max_attempts' => (int) env('APPLICANT_MAX_ATTEMPTS', 10),
            'window_minutes' => (int) env('APPLICANT_ATTEMPT_WINDOW', 15),
            'block_minutes' => (int) env('APPLICANT_BLOCK_MINUTES', 15),
        ],
    ],

    // Staff OTP (second factor).
    'otp' => [
        'enabled' => env('ADMIN_OTP_ENABLED', true),
        'expires_minutes' => 10,
        'max_attempts' => 5,
    ],

    // Idle timeout for staff sessions (minutes).
    'admin_idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 30),

    'contact' => [
        'email' => env('ADMISSIONS_EMAIL', 'admissions@ststephens.edu'),
        'phone' => env('ADMISSIONS_PHONE', '+91-11-27667271'),
        'address' => 'St. Stephen\'s College, University Enclave, Delhi - 110007',
    ],

    // Helpdesk quick replies (staff can insert and edit before sending).
    'quick_replies' => [
        'Thank you for writing to us. We are looking into your query and will reply shortly.',
        'Please check your spam/junk folder for the verification email. You can request a new link after logging in.',
        'If money was debited but your payment is not shown, wait 45 minutes and click "Check payment status" on your dashboard. Please do not pay again.',
        'Please make sure the CUET papers you count for a course match subjects you studied in Class 12, as required by the University of Delhi.',
        'Your application is under scrutiny. You will be informed by email if anything else is needed.',
    ],
];
