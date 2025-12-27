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
     * Relationships
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function escrowLock(): HasOne
    {
        return $this->hasOne(EscrowLock::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('order_status', 'ACTIVE');
    }

    public function scopeInEscrow($query)
    {
        return $query->where('order_status', 'IN_ESCROW');
    }

    public function scopeCompleted($query)
    {
        return $query->where('order_status', 'COMPLETED');
    }

    public function scopeDisputed($query)
    {
        return $query->where('order_status', 'DISPUTED');
    }

    public function scopeBySeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopeByBuyer($query, $buyerId)
    {
        return $query->where('buyer_id', $buyerId);
    }

    /**
     * Helper methods
     */
    public function isAvailable(): bool
    {
        return $this->order_status === 'ACTIVE' && is_null($this->buyer_id);
    }

    public function isInEscrow(): bool
    {
        return $this->order_status === 'IN_ESCROW';
    }

    public function isCompleted(): bool
    {
        return $this->order_status === 'COMPLETED';
    }

    public function isDisputed(): bool
    {
        return $this->order_status === 'DISPUTED';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->order_status, ['ACTIVE', 'IN_ESCROW']);
    }

    public function canBeDisputed(): bool
    {
        return $this->order_status === 'IN_ESCROW';
    }

    public function getPlatformFee(): float
    {
        $feePercentage = config('escrow.platform_fee_percentage', 2.5);
        return ($this->price * $feePercentage) / 100;
    }

    public function getSellerPayout(): float
    {
        return $this->price - $this->getPlatformFee();
    }
}
