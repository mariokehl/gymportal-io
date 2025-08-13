<?php

// config/sepa.php

return [
    /*
    |--------------------------------------------------------------------------
    | SEPA Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SEPA Direct Debit processing
    |
    */

    // Default creditor identifier (Gläubiger-Identifikationsnummer)
    'default_creditor_id' => env('SEPA_CREDITOR_ID', 'DE98ZZZ09999999999'),

    // Company information for mandates
    'creditor' => [
        'name' => env('SEPA_CREDITOR_NAME', env('APP_NAME', 'gymportal.io')),
        'address' => env('SEPA_CREDITOR_ADDRESS', 'Musterstraße 1'),
        'postal_code' => env('SEPA_CREDITOR_POSTAL_CODE', '12345'),
        'city' => env('SEPA_CREDITOR_CITY', 'Musterstadt'),
        'country' => env('SEPA_CREDITOR_COUNTRY', 'DE'),
    ],

    // Mandate settings
    'mandate' => [
        // Mandate validity period in months (36 months = 3 years as per SEPA rules)
        'validity_months' => 36,

        // Pre-notification period in days (minimum 14 days before collection)
        'pre_notification_days' => env('SEPA_PRE_NOTIFICATION_DAYS', 14),

        // Default sequence type for payments
        'default_sequence_type' => 'RCUR', // FRST (first), RCUR (recurring), OOFF (one-off), FNAL (final)

        // Reminder schedule for pending mandates (in days)
        'reminder_schedule' => [1, 3, 7, 14, 21, 28],

        // Auto-expire pending mandates after X days
        'auto_expire_pending_days' => 30,
    ],

    // Collection settings
    'collection' => [
        // Lead time for collections (days before due date)
        'lead_days' => env('SEPA_COLLECTION_LEAD_DAYS', 3),

        // Batch processing
        'batch_size' => env('SEPA_BATCH_SIZE', 100),

        // Retry settings for failed collections
        'retry' => [
            'enabled' => env('SEPA_RETRY_ENABLED', true),
            'max_attempts' => env('SEPA_RETRY_MAX_ATTEMPTS', 3),
            'delay_days' => env('SEPA_RETRY_DELAY_DAYS', 5),
        ],

        // Collection file format
        'file_format' => 'pain.008.001.02', // SEPA Direct Debit format
    ],

    // Bank account validation
    'validation' => [
        // Enable IBAN validation
        'validate_iban' => env('SEPA_VALIDATE_IBAN', true),

        // Enable BIC validation (optional for SEPA)
        'validate_bic' => env('SEPA_VALIDATE_BIC', false),

        // Allowed country codes for IBAN
        'allowed_countries' => [
            'AD', 'AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES',
            'FI', 'FR', 'GB', 'GI', 'GR', 'HR', 'HU', 'IE', 'IS', 'IT', 'LI',
            'LT', 'LU', 'LV', 'MC', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE',
            'SI', 'SK', 'SM', 'VA'
        ],
    ],

    // Notification settings
    'notifications' => [
        // Send pre-notification before collection
        'send_pre_notification' => env('SEPA_SEND_PRE_NOTIFICATION', true),

        // Send mandate confirmation
        'send_mandate_confirmation' => env('SEPA_SEND_MANDATE_CONFIRMATION', true),

        // Send collection confirmation
        'send_collection_confirmation' => env('SEPA_SEND_COLLECTION_CONFIRMATION', false),

        // Admin notifications
        'admin_notifications' => [
            'enabled' => env('SEPA_ADMIN_NOTIFICATIONS', true),
            'email' => env('SEPA_ADMIN_EMAIL', env('ADMIN_EMAIL')),
            'on_mandate_signed' => true,
            'on_collection_failed' => true,
            'on_mandate_expired' => true,
        ],
    ],

    // File storage
    'storage' => [
        // Storage disk for mandate PDFs and collection files
        'disk' => env('SEPA_STORAGE_DISK', 'local'),

        // Path for mandate documents
        'mandate_path' => 'sepa/mandates',

        // Path for collection files
        'collection_path' => 'sepa/collections',

        // Path for reports
        'report_path' => 'sepa/reports',

        // Keep files for X months
        'retention_months' => 84, // 7 years for tax purposes
    ],

    // Legal texts (can be overridden per language)
    'texts' => [
        'de' => [
            'mandate_authorization' => 'Ich ermächtige den Zahlungsempfänger, Zahlungen von meinem Konto mittels Lastschrift einzuziehen. Zugleich weise ich mein Kreditinstitut an, die vom Zahlungsempfänger auf mein Konto gezogenen Lastschriften einzulösen.',
            'mandate_note' => 'Hinweis: Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des belasteten Betrages verlangen. Es gelten dabei die mit meinem Kreditinstitut vereinbarten Bedingungen.',
            'pre_notification' => 'Wir werden den fälligen Betrag von :amount EUR am :date von Ihrem Konto einziehen.',
        ],
        'en' => [
            'mandate_authorization' => 'I authorize the creditor to collect payments from my account by direct debit. At the same time, I instruct my bank to honor the direct debits drawn on my account by the creditor.',
            'mandate_note' => 'Note: I can demand a refund of the amount charged within eight weeks, starting from the debit date. The terms agreed with my bank apply.',
            'pre_notification' => 'We will collect the amount of :amount EUR from your account on :date.',
        ],
    ],

    // Test mode settings
    'testing' => [
        // Enable test mode
        'enabled' => env('SEPA_TEST_MODE', false),

        // Test IBAN for validation
        'test_iban' => 'DE89370400440532013000',

        // Skip actual bank communication in test mode
        'skip_bank_communication' => true,
    ],

    // Error handling
    'errors' => [
        // Log all errors
        'log_errors' => true,

        // Send error notifications to admin
        'notify_admin' => true,

        // Quarantine failed mandates after X failures
        'quarantine_after_failures' => 5,
    ],

    // Compliance settings
    'compliance' => [
        // Enable audit logging
        'audit_logging' => true,

        // PCI DSS compliance mode (masks sensitive data in logs)
        'pci_dss_mode' => env('SEPA_PCI_DSS_MODE', true),

        // GDPR compliance
        'gdpr' => [
            'enabled' => true,
            'data_retention_days' => 2555, // 7 years
            'allow_data_export' => true,
            'allow_data_deletion' => true,
        ],
    ],

    // Return codes and their meanings
    'return_codes' => [
        'AC01' => 'Incorrect Account Number',
        'AC04' => 'Closed Account Number',
        'AC06' => 'Blocked Account',
        'AG01' => 'Transaction Forbidden',
        'AG02' => 'Invalid Bank Operation Code',
        'AM04' => 'Insufficient Funds',
        'AM05' => 'Duplicate Collection',
        'BE01' => 'Inconsistent With Customer Name',
        'BE05' => 'Unrecognised Initiator',
        'FF01' => 'Invalid File Format',
        'MD01' => 'No Mandate',
        'MD02' => 'Missing Mandatory Information',
        'MD06' => 'Disputed Authorised Transaction',
        'MD07' => 'End Customer Deceased',
        'MS02' => 'Not Specified Reason Customer Generated',
        'MS03' => 'Not Specified Reason Agent Generated',
        'RC01' => 'Bank Identifier Incorrect',
        'RR01' => 'Missing Debtor Account Or Identification',
        'RR02' => 'Missing Debtors Name Or Address',
        'RR03' => 'Missing Creditors Name Or Address',
        'RR04' => 'Regulatory Reason',
        'SL01' => 'Specific Service Offered By Debtor Bank',
    ],
];
