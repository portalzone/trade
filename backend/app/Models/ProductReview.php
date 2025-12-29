<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'title',
        'comment',
        'images',
        'is_verified_purchase',
        'is_approved',
        'helpful_count',
        'not_helpful_count',
        'seller_response',
        'seller_responded_at',
        'approved_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'images' => 'array',
        'is_verified_purchase' => 'boolean',
        'is_approved' => 'boolean',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
        'seller_responded_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(StorefrontProduct::class, 'product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class, 'review_id');
    }

    /**
     * Check if review is approved
     */
    public function isApproved(): bool
    {
        return $this->is_approved === true;
    }

    /**
     * Check if review has seller response
     */
    public function hasSellerResponse(): bool
    {
        return !empty($this->seller_response);
    }

    /**
     * Get helpfulness percentage
     */
    public function getHelpfulnessPercentage(): float
    {
        $total = $this->helpful_count + $this->not_helpful_count;
        
        if ($total === 0) {
            return 0;
        }

        return round(($this->helpful_count / $total) * 100, 1);
    }

    /**
     * Increment helpful count
     */
    public function incrementHelpful(): void
    {
        $this->increment('helpful_count');
    }

    /**
     * Decrement helpful count
     */
    public function decrementHelpful(): void
    {
        $this->decrement('helpful_count');
    }

    /**
     * Increment not helpful count
     */
    public function incrementNotHelpful(): void
    {
        $this->increment('not_helpful_count');
    }

    /**
     * Decrement not helpful count
     */
    public function decrementNotHelpful(): void
    {
        $this->decrement('not_helpful_count');
    }
}
