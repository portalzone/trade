<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway used for transactions.
    | Options: 'paystack', 'stripe'
    |
    */
    'default' => env('PAYMENT_GATEWAY', 'paystack'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    */
    'gateways' => [
        'paystack' => [
            'enabled' => env('PAYSTACK_ENABLED', true),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL'),
            'callback_url' => env('APP_URL') . '/api/payments/paystack/callback',
            'webhook_url' => env('APP_URL') . '/api/webhooks/paystack',
        ],

        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', true),
            'public_key' => env('STRIPE_PUBLIC_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'callback_url' => env('APP_URL') . '/api/payments/stripe/callback',
            'webhook_url' => env('APP_URL') . '/api/webhooks/stripe',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Settings
    |--------------------------------------------------------------------------
    */
    'min_deposit' => env('MIN_DEPOSIT_AMOUNT', 100), // ₦100 or $1
    'max_deposit' => env('MAX_DEPOSIT_AMOUNT', 1000000), // ₦1M or $10,000
    'min_withdrawal' => env('MIN_WITHDRAWAL_AMOUNT', 500), // ₦500 or $5
    'max_withdrawal' => env('MAX_WITHDRAWAL_AMOUNT', 500000), // ₦500K or $5,000

    /*
    |--------------------------------------------------------------------------
    | Fee Structure
    |--------------------------------------------------------------------------
    */
    'fees' => [
        'deposit' => [
            'paystack' => 0.015, // 1.5%
            'stripe' => 0.029, // 2.9%
        ],
        'withdrawal' => [
            'flat_fee' => 100, // ₦100 or $1
            'percentage' => 0.01, // 1%
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    */
    'currencies' => [
        'NGN' => [
            'symbol' => '₦',
            'gateway' => 'paystack',
        ],
        'USD' => [
            'symbol' => '$',
            'gateway' => 'stripe',
        ],
    ],

    'default_currency' => env('DEFAULT_CURRENCY', 'NGN'),
];
