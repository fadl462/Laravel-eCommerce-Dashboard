<?php

/**
 * NOT a standalone config file — `laravel new` already ships a config/services.php
 * with entries for mail/aws/slack etc. Copy the array entries below INTO that
 * existing file's returned array; don't replace the whole file with this one.
 */
return [

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],

    'bank_transfer' => [
        'bank_name' => env('BANK_NAME'),
        'account_name' => env('BANK_ACCOUNT_NAME'),
        'account_number' => env('BANK_ACCOUNT_NUMBER'),
        'iban' => env('BANK_IBAN'),
        'swift' => env('BANK_SWIFT'),
    ],

];
