<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'amount',
        'currency',
        'slug',
        'status',
        'max_uses',
        'current_uses',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'max_uses' => 'integer',
        'current_uses' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentLinkPayment::class);
    }

    /**
     * Check if payment link is active
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Check expiration
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Check max uses
        if ($this->max_uses && $this->current_uses >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Get public URL
     */
    public function getUrlAttribute(): string
    {
        return url("/pay/{$this->slug}");
    }

    /**
     * Generate unique slug
     */
    public static function generateSlug(): string
    {
        do {
            $slug = Str::random(10);
        } while (self::where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * Increment usage count
     */
    public function incrementUses(): void
    {
        $this->increment('current_uses');

        // Auto-disable if max uses reached
        if ($this->max_uses && $this->current_uses >= $this->max_uses) {
            $this->update(['status' => 'inactive']);
        }
    }
}
