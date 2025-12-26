<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Fee Configuration
    |--------------------------------------------------------------------------
    |
    | Platform fee charged on successful order completion
    |
    */
    'platform_fee_percentage' => env('ESCROW_PLATFORM_FEE', 2.5),

    /*
    |--------------------------------------------------------------------------
    | Order Amount Limits
    |--------------------------------------------------------------------------
    |
    | Minimum and maximum order amounts
    |
    */
    'min_order_amount' => env('ESCROW_MIN_ORDER', 500),      // ₦500 minimum
    'max_order_amount' => env('ESCROW_MAX_ORDER', 5000000),  // ₦5M maximum

    /*
    |--------------------------------------------------------------------------
    | Auto-completion Settings
    |--------------------------------------------------------------------------
    |
    | Number of days before order auto-completes if buyer doesn't confirm
    |
    */
    'auto_complete_days' => env('ESCROW_AUTO_COMPLETE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Dispute Review Period
    |--------------------------------------------------------------------------
    |
    | Maximum days for admin to review and resolve disputes
    |
    */
    'dispute_review_days' => env('ESCROW_DISPUTE_REVIEW_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Cancellation Rules
    |--------------------------------------------------------------------------
    |
    */
    'allow_seller_cancel_after_purchase' => false,  // Seller can't cancel once purchased
    'allow_buyer_cancel_after_purchase' => true,    // Buyer can request cancellation
    'require_both_parties_for_cancel' => true,      // Need both to agree on cancellation

    /*
    |--------------------------------------------------------------------------
    | Order Statuses
    |--------------------------------------------------------------------------
    |
    */
    'statuses' => [
        'ACTIVE' => 'Listed and available for purchase',
        'PENDING_PAYMENT' => 'Buyer initiated purchase',
        'IN_ESCROW' => 'Funds locked, awaiting delivery',
        'COMPLETED' => 'Successfully completed',
        'CANCELLED' => 'Order cancelled',
        'DISPUTED' => 'Under dispute',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dispute Statuses
    |--------------------------------------------------------------------------
    |
    */
    'dispute_statuses' => [
        'OPEN' => 'Dispute raised, awaiting review',
        'UNDER_REVIEW' => 'Admin reviewing dispute',
        'RESOLVED_BUYER' => 'Resolved in favor of buyer (refund)',
        'RESOLVED_SELLER' => 'Resolved in favor of seller (release)',
        'RESOLVED_REFUND' => 'Partial refund to both parties',
    ],
];
