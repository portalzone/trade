<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wishlist;
use App\Models\RecentlyViewed;
use App\Models\StorefrontProduct;
use Illuminate\Support\Facades\DB;

class UserFeaturesService
{
    /**
     * Add product to wishlist
     */
    public function addToWishlist(User $user, int $productId): Wishlist
    {
        $product = StorefrontProduct::findOrFail($productId);

        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $user->id,
            'product_id' => $productId,
        ]);

        AuditService::log(
            'wishlist.added',
            "Product added to wishlist: {$product->name}",
            $wishlist,
            [],
            ['product_id' => $productId],
            ['user_id' => $user->id]
        );

        return $wishlist;
    }

    /**
     * Remove product from wishlist
     */
    public function removeFromWishlist(User $user, int $productId): bool
    {
        $deleted = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->delete();

        if ($deleted) {
            AuditService::log(
                'wishlist.removed',
                "Product removed from wishlist",
                null,
                [],
                ['product_id' => $productId],
                ['user_id' => $user->id]
            );
        }

        return $deleted > 0;
    }

    /**
     * Get user's wishlist
     */
    public function getWishlist(User $user)
    {
        return Wishlist::where('user_id', $user->id)
            ->with(['product.category', 'product.storefront'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->pluck('product');
    }

    /**
     * Check if product is in wishlist
     */
    public function isInWishlist(User $user, int $productId): bool
    {
        return Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * Track recently viewed product
     */
    public function trackView(?User $user, int $productId, ?string $sessionId = null): void
    {
        $product = StorefrontProduct::findOrFail($productId);

        // Delete old view from same user/session
        if ($user) {
            RecentlyViewed::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->delete();
        } elseif ($sessionId) {
            RecentlyViewed::where('session_id', $sessionId)
                ->where('product_id', $productId)
                ->delete();
        }

        // Create new view
        RecentlyViewed::create([
            'user_id' => $user?->id,
            'session_id' => $sessionId,
            'product_id' => $productId,
            'viewed_at' => now(),
        ]);

        // Keep only last 50 views per user/session
        $this->cleanupOldViews($user, $sessionId);
    }

    /**
     * Get recently viewed products
     */
    public function getRecentlyViewed(?User $user, ?string $sessionId = null, int $limit = 20)
    {
        $query = RecentlyViewed::query()
            ->with(['product.category', 'product.storefront']);

        if ($user) {
            $query->where('user_id', $user->id);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return collect([]);
        }

        return $query->orderBy('viewed_at', 'desc')
            ->limit($limit)
            ->get()
            ->pluck('product')
            ->unique('id')
            ->values();
    }

    /**
     * Clean up old views
     */
    protected function cleanupOldViews(?User $user, ?string $sessionId): void
    {
        $query = RecentlyViewed::query();

        if ($user) {
            $query->where('user_id', $user->id);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return;
        }

        $keepIds = (clone $query)
            ->orderBy('viewed_at', 'desc')
            ->limit(50)
            ->pluck('id');

        $query->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * Get best sellers
     */
    public function getBestSellers(int $limit = 10, ?int $days = 30)
    {
        return StorefrontProduct::where('is_active', true)
            ->where('sales_count', '>', 0)
            ->with(['category', 'storefront'])
            ->orderBy('sales_count', 'desc')
            ->orderBy('average_rating', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get trending products (most viewed)
     */
    public function getTrendingProducts(int $limit = 10, int $hours = 24)
    {
        $since = now()->subHours($hours);

        $productIds = RecentlyViewed::where('viewed_at', '>=', $since)
            ->select('product_id', DB::raw('COUNT(*) as view_count'))
            ->groupBy('product_id')
            ->orderBy('view_count', 'desc')
            ->limit($limit)
            ->pluck('product_id');

        return StorefrontProduct::whereIn('id', $productIds)
            ->where('is_active', true)
            ->with(['category', 'storefront'])
            ->get();
    }

    /**
     * Get top rated products
     */
    public function getTopRated(int $limit = 10, float $minRating = 4.0)
    {
        return StorefrontProduct::where('is_active', true)
            ->where('average_rating', '>=', $minRating)
            ->where('reviews_count', '>', 0)
            ->with(['category', 'storefront'])
            ->orderBy('average_rating', 'desc')
            ->orderBy('reviews_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Compare products
     */
    public function compareProducts(array $productIds): array
    {
        if (count($productIds) > 4) {
            throw new \Exception('Maximum 4 products can be compared');
        }

        $products = StorefrontProduct::whereIn('id', $productIds)
            ->where('is_active', true)
            ->with(['category', 'storefront', 'reviews'])
            ->get();

        return $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'compare_at_price' => $product->compare_at_price,
                'discount' => $product->getDiscountPercentage(),
                'rating' => $product->average_rating,
                'reviews_count' => $product->reviews_count,
                'stock_status' => $product->stock_status,
                'category' => $product->category?->name,
                'storefront' => $product->storefront->name,
                'images' => $product->images,
                'description' => $product->description,
                'weight' => $product->weight,
                'dimensions' => $product->dimensions,
            ];
        })->toArray();
    }
}
