<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'buyer_id',
        'title',
        'description',
        'price',
        'currency',
        'category',
        'images',
        'order_status',
        'escrow_locked_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'images' => 'array',
        'escrow_locked_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the seller who created this order
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Get the buyer who purchased this order
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Get escrow lock for this order
     */
    public function escrowLock(): HasOne
    {
        return $this->hasOne(EscrowLock::class);
    }

    /**
     * Get disputes for this order
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /**
     * Scope: Active orders (available for purchase)
     */
    public function scopeActive($query)
    {
        return $query->where('order_status', 'ACTIVE');
    }

    /**
     * Scope: Orders in escrow
     */
    public function scopeInEscrow($query)
    {
        return $query->where('order_status', 'IN_ESCROW');
    }

    /**
     * Scope: Completed orders
     */
    public function scopeCompleted($query)
    {
        return $query->where('order_status', 'COMPLETED');
    }

    /**
     * Scope: Disputed orders
     */
    public function scopeDisputed($query)
    {
        return $query->where('order_status', 'DISPUTED');
    }

    /**
     * Scope: Orders by seller
     */
    public function scopeBySeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    /**
     * Scope: Orders by buyer
     */
    public function scopeByBuyer($query, $buyerId)
    {
        return $query->where('buyer_id', $buyerId);
    }

    /**
     * Check if order is available for purchase
     */
    public function isAvailable(): bool
    {
        return $this->order_status === 'ACTIVE' && is_null($this->buyer_id);
    }

    /**
     * Check if order is in escrow
     */
    public function isInEscrow(): bool
    {
        return $this->order_status === 'IN_ESCROW';
    }

    /**
     * Check if order is completed
     */
    public function isCompleted(): bool
    {
        return $this->order_status === 'COMPLETED';
    }

    /**
     * Check if order is disputed
     */
    public function isDisputed(): bool
    {
        return $this->order_status === 'DISPUTED';
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->order_status, ['ACTIVE', 'IN_ESCROW']);
    }

    /**
     * Check if order can be disputed
     */
    public function canBeDisputed(): bool
    {
        return $this->order_status === 'IN_ESCROW';
    }

    /**
     * Get platform fee for this order
     */
    public function getPlatformFee(): float
    {
        $feePercentage = config('escrow.platform_fee_percentage', 2.5);
        return ($this->price * $feePercentage) / 100;
    }

    /**
     * Get seller payout amount (price - platform fee)
     */
    public function getSellerPayout(): float
    {
        return $this->price - $this->getPlatformFee();
    }
}
