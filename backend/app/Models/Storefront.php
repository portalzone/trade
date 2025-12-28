<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Storefront extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'subdomain',
        'description',
        'logo_url',
        'banner_url',
        'primary_color',
        'secondary_color',
        'accent_color',
        'theme',
        'currency',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'country',
        'social_links',
        'business_hours',
        'status',
        'is_verified',
        'total_products',
        'total_sales',
        'total_revenue',
        'average_rating',
        'total_reviews',
        'verified_at',
    ];

    protected $casts = [
        'social_links' => 'array',
        'business_hours' => 'array',
        'is_verified' => 'boolean',
        'total_products' => 'integer',
        'total_sales' => 'integer',
        'total_revenue' => 'decimal:2',
        'average_rating' => 'decimal:2',
        'total_reviews' => 'integer',
        'verified_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from name
        static::creating(function ($storefront) {
            if (empty($storefront->slug)) {
                $storefront->slug = Str::slug($storefront->name);
                
                // Ensure uniqueness
                $originalSlug = $storefront->slug;
                $count = 1;
                while (static::where('slug', $storefront->slug)->exists()) {
                    $storefront->slug = $originalSlug . '-' . $count++;
                }
            }

            // Auto-generate subdomain from slug
            if (empty($storefront->subdomain)) {
                $storefront->subdomain = $storefront->slug;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(StorefrontProduct::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(StorefrontAnalytics::class);
    }

    /**
     * Check if storefront is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if storefront is verified
     */
    public function isVerified(): bool
    {
        return $this->is_verified === true;
    }

    /**
     * Get full storefront URL
     */
    public function getUrl(): string
    {
        return "https://{$this->subdomain}.t-trade.ng";
    }

    /**
     * Increment product count
     */
    public function incrementProducts(): void
    {
        $this->increment('total_products');
    }

    /**
     * Decrement product count
     */
    public function decrementProducts(): void
    {
        $this->decrement('total_products');
    }

    /**
     * Record a sale
     */
    public function recordSale(float $amount): void
    {
        $this->increment('total_sales');
        $this->increment('total_revenue', $amount);
    }
}
