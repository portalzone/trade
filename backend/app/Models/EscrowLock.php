<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscrowLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'wallet_id',
        'amount',
        'platform_fee',
        'lock_type',
        'locked_at',
        'released_at',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'locked_at' => 'datetime',
        'released_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Get the order this escrow lock belongs to
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the wallet this escrow lock belongs to
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Check if escrow is locked
     */
    public function isLocked(): bool
    {
        return !is_null($this->locked_at) && is_null($this->released_at) && is_null($this->refunded_at);
    }

    /**
     * Check if escrow is released
     */
    public function isReleased(): bool
    {
        return !is_null($this->released_at);
    }

    /**
     * Check if escrow is refunded
     */
    public function isRefunded(): bool
    {
        return !is_null($this->refunded_at);
    }
}
