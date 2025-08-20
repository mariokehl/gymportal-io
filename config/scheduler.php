<?php

return [
    //'enabled' => env('SCHEDULER_ENABLED', true),
    //'test_mode' => env('SCHEDULER_TEST_MODE', false),
    //'days_ahead' => env('SCHEDULER_DAYS_AHEAD', 30),
    //'max_retries' => env('SCHEDULER_MAX_RETRIES', 3),
    //'use_queue' => env('SCHEDULER_USE_QUEUE', false),

    //'payment' => [
    //    'sepa_lead_days' => env('PAYMENT_SEPA_LEAD_DAYS', 3),
    //    'invoice_due_days' => env('PAYMENT_INVOICE_DUE_DAYS', 14),
    //    'retry_interval_hours' => env('PAYMENT_RETRY_INTERVAL_HOURS', 24),
    //],

    'notifications' => [
        //'expiry_days' => explode(',', env('NOTIFICATION_EXPIRY_DAYS', '30,14,7,3,1')),
        'admin_email' => env('NOTIFICATION_ADMIN_EMAIL', 'webmaster@gymportal.io'),
    ],
];
