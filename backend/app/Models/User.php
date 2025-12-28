<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'email',
        'phone_number',
        'password_hash',
        'full_name',
        'username',
        'profile_photo_url',
        'user_type',
        'kyc_status',
        'kyc_tier',
        'is_express_vendor',
        'is_rider',
        'mfa_enabled',
        'mfa_method',
        'account_status',
        'kyc_submitted_at',
        'kyc_approved_at',
        'kyc_rejected_at',
        'kyc_rejection_reason',
        'food_safety_cert_expiry',
        'driver_license_expiry',
        'background_check_expiry',
        'last_login_at',
        'last_location_update_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password_hash',
        'mfa_method',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_express_vendor' => 'boolean',
        'is_rider' => 'boolean',
        'mfa_enabled' => 'boolean',
        'kyc_tier' => 'integer',
        'kyc_submitted_at' => 'datetime',
        'kyc_approved_at' => 'datetime',
        'kyc_rejected_at' => 'datetime',
        'food_safety_cert_expiry' => 'date',
        'driver_license_expiry' => 'date',
        'background_check_expiry' => 'date',
        'last_login_at' => 'datetime',
        'last_location_update_at' => 'datetime',
    ];

    /**
     * Override password attribute for hashing
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password_hash'] = bcrypt($value);
    }

    /**
     * Get the password for authentication (Laravel expects 'password' attribute)
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Get the wallet associated with the user (1:1)
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get orders where user is the buyer
     */
    public function ordersAsBuyer()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    /**
     * Get orders where user is the seller
     */
    public function ordersAsSeller()
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    /**
     * Get disputes opened by this user
     */
    public function disputes()
    {
        return $this->hasMany(Dispute::class, 'opened_by_user_id');
    }

    /**
     * Get rider profile if user is a rider
     */
    public function riderProfile()
    {
        return $this->hasOne(Rider::class);
    }

    /**
     * Get user's storefront
     */
    public function storefront()
    {
        return $this->hasOne(\App\Models\Storefront::class);
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope to filter by user type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('user_type', $type);
    }

    /**
     * Scope to filter by KYC tier
     */
    public function scopeOfTier($query, int $tier)
    {
        return $query->where('kyc_tier', $tier);
    }

    /**
     * Scope to get only active users
     */
    public function scopeActive($query)
    {
        return $query->where('account_status', 'ACTIVE');
    }

    /**
     * Scope to get verified users
     */
    public function scopeVerified($query)
    {
        return $query->whereIn('kyc_status', [
            'BASIC_VERIFIED',
            'BUSINESS_VERIFIED',
            'ENTERPRISE_VERIFIED',
            'EXPRESS_VENDOR_VERIFIED'
        ]);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if user is a seller
     */
    public function isSeller(): bool
    {
        return $this->user_type === 'SELLER';
    }

    /**
     * Check if user is a buyer
     */
    public function isBuyer(): bool
    {
        return $this->user_type === 'BUYER';
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return in_array($this->user_type, ['ADMIN', 'SUPPORT', 'MEDIATOR']);
    }

    /**
     * Check if user's KYC is verified
     */
    public function isKycVerified(): bool
    {
        return in_array($this->kyc_status, [
            'BASIC_VERIFIED',
            'BUSINESS_VERIFIED',
            'ENTERPRISE_VERIFIED',
            'EXPRESS_VENDOR_VERIFIED'
        ]);
    }

    /**
     * Get transaction limits based on tier
     */
    public function getTransactionLimits(): array
    {
        return match($this->kyc_tier) {
            1 => [
                'per_transaction' => config('limits.tier_1.per_transaction', 100000),
                'daily' => config('limits.tier_1.daily', 200000),
                'monthly' => config('limits.tier_1.monthly', 500000),
            ],
            2 => [
                'per_transaction' => config('limits.tier_2.per_transaction', 500000),
                'daily' => config('limits.tier_2.daily', 2000000),
                'monthly' => config('limits.tier_2.monthly', 20000000),
            ],
            3 => [
                'per_transaction' => PHP_INT_MAX, // Unlimited
                'daily' => PHP_INT_MAX,
                'monthly' => PHP_INT_MAX,
            ],
            default => [
                'per_transaction' => 0,
                'daily' => 0,
                'monthly' => 0,
            ]
        };
    }

    /**
     * Check if user can perform a transaction of given amount
     */
    public function canTransact(float $amount): bool
    {
        $limits = $this->getTransactionLimits();
        
        // Check per-transaction limit
        if ($amount > $limits['per_transaction']) {
            return false;
        }

        // TODO: Check daily and monthly limits against actual transaction history
        // This would require querying the orders table
        
        return true;
    }

    /**
     * Update last login timestamp
     */
    public function recordLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Check if MFA should be required for this user
     */
    public function requiresMfa(): bool
    {
        // Admins and Tier 3 sellers must use MFA
        return $this->isAdmin() || $this->kyc_tier === 3;
    }

    /**
     * Get full name with tier badge for display
     */
    public function getDisplayNameAttribute(): string
    {
        $badges = [
            1 => '',
            2 => ' 🏢',  // Business
            3 => ' ⭐',  // Enterprise
        ];

        return $this->full_name . ($badges[$this->kyc_tier] ?? '');
    }
}
