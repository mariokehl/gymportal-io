<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credit Ledger HMAC Key
    |--------------------------------------------------------------------------
    |
    | Dedicated secret used to sign the tamper-evident HMAC chain of the member
    | credit ledger. It protects the integrity of stored balances so a third
    | party with raw database access cannot alter an amount (e.g. turn 3 € into
    | 300 €) without breaking the chain.
    |
    | Generate one with: php -r "echo base64_encode(random_bytes(32));"
    | If unset, the application falls back to APP_KEY, but a dedicated key is
    | strongly recommended so credit integrity survives an APP_KEY rotation.
    |
    */

    'hmac_key' => env('CREDIT_LEDGER_HMAC_KEY'),

];
