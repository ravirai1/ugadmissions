<?php

return [
    // Requests that look like an attack are refused (403) and saved in security_events.
    'detect_attacks' => env('SECURITY_DETECT_ATTACKS', true),

    // An IP with this many attack attempts in the window is blocked from the whole site.
    'auto_block' => [
        'max_events' => (int) env('SECURITY_BLOCK_AFTER', 5),
        'window_minutes' => (int) env('SECURITY_BLOCK_WINDOW', 60),
        'block_minutes' => (int) env('SECURITY_BLOCK_MINUTES', 1440),
    ],

    // Security alert emails. Comma separated; when empty, every active super admin gets them.
    'alert_emails' => array_filter(array_map('trim', explode(',', (string) env('SECURITY_ALERT_EMAILS', '')))),

    // The same kind of alert (for the same IP or payment) is emailed at most once in this many minutes.
    'alert_throttle_minutes' => (int) env('SECURITY_ALERT_THROTTLE', 30),

    // Form fields never inspected or stored (secrets).
    'skip_fields' => ['password', 'password_confirmation', 'current_password', 'new_password', 'otp', 'code', 'captcha', '_token', 'encData', 'encdata', 'merchIdVal'],

    // Paths that receive encrypted gateway payloads.
    'skip_paths' => ['payment/sbi/response', 'payment/sbi/push', 'payment/fake'],
];
