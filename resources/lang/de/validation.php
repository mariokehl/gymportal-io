<?php

return [
    'required' => ':attribute ist erforderlich.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'min' => [
        'string' => ':attribute muss mindestens :min Zeichen haben.',
    ],
    'max' => [
        'string' => ':attribute darf maximal :max Zeichen haben.',
    ],
    'confirmed' => 'Die :attribute-Bestätigung stimmt nicht überein.',
    'unique' => ':attribute ist bereits vergeben.',
    'indisposable' => 'Wegwerf-E-Mail-Adressen sind nicht erlaubt.',

    'attributes' => [
        'email' => 'E-Mail-Adresse',
        'password' => 'Passwort',
        'name' => 'Name',
        'password_confirmation' => 'Passwort-Bestätigung',
    ],
];
