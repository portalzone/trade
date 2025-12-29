<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StorefrontProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'storefront_id',
        'category_id',
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'price',
        'compare_at_price',
        'cost_price',
        'stock_quantity',
        'low_stock_threshold',
        'track_inventory',
        'stock_status',
        'images',
        'variants',
        'weight',
        'dimensions',
        'is_featured',
        'is_active',
        'views_count',
        'sales_count',
        'average_rating',
        'reviews_count',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'published_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'track_inventory' => 'boolean',
        'images' => 'array',
        'variants' => 'array',
        'dimensions' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'views_count' => 'integer',
        'sales_count' => 'integer',
        'average_rating' => 'decimal:2',
        'reviews_count' => 'integer',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'meta_keywords' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }

            if (empty($product->sku)) {
                $product->sku = 'PRD-' . strtoupper(Str::random(8));
            }

            if ($product->is_active && empty($product->published_at)) {
                $product->published_at = now();
            }

            $product->updateStockStatus();
        });

        static::updating(function ($product) {
            $product->updateStockStatus();
        });
    }

    public function storefront(): BelongsTo
    {
        return $this->belongsTo(Storefront::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /**
     * Get product reviews
     */
    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }

    /**
     * Get approved reviews only
     */
    public function approvedReviews()
    {
        return $this->hasMany(ProductReview::class, 'product_id')
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Update stock status based on quantity
     */
    public function updateStockStatus(): void
    {
        if (!$this->track_inventory) {
            $this->stock_status = 'in_stock';
            return;
        }

        if ($this->stock_quantity <= 0) {
            $this->stock_status = 'out_of_stock';
        } elseif ($this->stock_quantity <= $this->low_stock_threshold) {
            $this->stock_status = 'low_stock';
        } else {
            $this->stock_status = 'in_stock';
        }
    }

    /**
     * Check if product is in stock
     */
    public function isInStock(): bool
    {
        return $this->stock_status === 'in_stock';
    }

    /**
     * Check if product is active
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if product is featured
     */
    public function isFeatured(): bool
    {
        return $this->is_featured === true;
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentage(): ?float
    {
        if (!$this->compare_at_price || $this->compare_at_price <= $this->price) {
            return null;
        }

        return round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100, 2);
    }

    /**
     * Increment views
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    /**
     * Record a sale
     */
    public function recordSale(int $quantity = 1): void
    {
        $this->increment('sales_count', $quantity);
        
        if ($this->track_inventory) {
            $this->decrement('stock_quantity', $quantity);
            $this->updateStockStatus();
            $this->save();
        }
    }

    /**
     * Recalculate average rating
     */
    public function recalculateRating(): void
    {
        $stats = $this->reviews()
            ->where('is_approved', true)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_reviews')
            ->first();

        $this->update([
            'average_rating' => $stats->avg_rating ?? 0,
            'reviews_count' => $stats->total_reviews ?? 0,
        ]);
    }
}