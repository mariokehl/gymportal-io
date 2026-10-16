<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Guests Portal URL
    |--------------------------------------------------------------------------
    |
    | The base URL of the separate guests portal application. This is an
    | independent frontend with its own URL, used for guest purchases and
    | payment redirects. Set GUESTS_URL in your .env to override.
    |
    */

    'guests_url' => env('GUESTS_URL', 'https://guests.gymportal.io'),

];
