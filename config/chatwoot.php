<?php

return [
    'website_token' => env('CHATWOOT_WEBSITE_TOKEN'),
    'base_url' => env('CHATWOOT_BASE_URL', 'https://app.chatwoot.com'),
    'enabled' => env('CHATWOOT_ENABLED', true),
    'identity_validation_secret' => env('CHATWOOT_IDENTITY_VALIDATION_SECRET', ''),
];
