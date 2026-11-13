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
];
