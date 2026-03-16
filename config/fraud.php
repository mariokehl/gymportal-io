<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fraud Detection Schwellenwerte
    |--------------------------------------------------------------------------
    */

    // Score ab dem eine Registrierung automatisch abgelehnt wird
    'block_threshold' => env('FRAUD_BLOCK_THRESHOLD', 80),

    // Score ab dem eine Registrierung zur manuellen Prüfung markiert wird
    'flag_threshold' => env('FRAUD_FLAG_THRESHOLD', 40),

    // Maximale Levenshtein-Distanz für Namens-Fuzzy-Matching
    'name_levenshtein_max' => env('FRAUD_NAME_LEVENSHTEIN_MAX', 2),

    // Anzahl fehlgeschlagener Zahlungen bevor automatisch gesperrt wird
    'block_after_failures' => env('FRAUD_BLOCK_AFTER_FAILURES', 1),

    // Sperrlisten-Einträge nach X Tagen löschen (DSGVO)
    'blocklist_expire_days' => env('FRAUD_BLOCKLIST_EXPIRE_DAYS', 1095), // 3 Jahre

    // Salt für Identifier-Hashing (nie APP_KEY verwenden!)
    'salt' => env('APP_FRAUD_SALT', ''),
];
