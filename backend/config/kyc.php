<?php

return [
    /*
    |--------------------------------------------------------------------------
    | KYC Provider Configuration
    |--------------------------------------------------------------------------
    */

    'nin_provider' => env('KYC_NIN_PROVIDER', 'dojah'),
    'bvn_provider' => env('KYC_BVN_PROVIDER', 'dojah'),

    'providers' => [
        'dojah' => [
            'api_key' => env('DOJAH_API_KEY'),
            'app_id' => env('DOJAH_APP_ID'),
            'base_url' => env('DOJAH_BASE_URL', 'https://api.dojah.io'),
            'public_key' => env('DOJAH_PUBLIC_KEY'),
        ],
        
        'youverify' => [
            'api_key' => env('YOUVERIFY_API_KEY'),
            'base_url' => env('YOUVERIFY_BASE_URL', 'https://api.youverify.co'),
        ],
        
        'prembly' => [
            'api_key' => env('PREMBLY_API_KEY'),
            'app_id' => env('PREMBLY_APP_ID'),
            'base_url' => env('PREMBLY_BASE_URL', 'https://api.myidentitypass.com'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Settings
    |--------------------------------------------------------------------------
    */

    'max_attempts_per_hour' => 3,
    'manual_review_threshold' => 3,
    'auto_approve_match_threshold' => 90, // percentage match for auto-approval
];
