<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'storefront_id',
        'date',
        'page_views',
        'unique_visitors',
        'product_views',
        'add_to_cart',
        'orders',
        'revenue',
        'conversion_rate',
        'top_products',
        'traffic_sources',
    ];

    protected $casts = [
        'date' => 'date',
        'page_views' => 'integer',
        'unique_visitors' => 'integer',
        'product_views' => 'integer',
        'add_to_cart' => 'integer',
        'orders' => 'integer',
        'revenue' => 'decimal:2',
        'conversion_rate' => 'decimal:2',
        'top_products' => 'array',
        'traffic_sources' => 'array',
    ];

    public function storefront(): BelongsTo
    {
        return $this->belongsTo(Storefront::class);
    }

    /**
     * Record a page view
     */
    public static function recordPageView(int $storefrontId, bool $isUnique = false): void
    {
        $analytics = static::firstOrCreate([
            'storefront_id' => $storefrontId,
            'date' => now()->toDateString(),
        ]);

        $analytics->increment('page_views');
        
        if ($isUnique) {
            $analytics->increment('unique_visitors');
        }
    }

    /**
     * Calculate conversion rate
     */
    public function calculateConversionRate(): void
    {
        if ($this->unique_visitors > 0) {
            $this->conversion_rate = ($this->orders / $this->unique_visitors) * 100;
            $this->save();
        }
    }
}
