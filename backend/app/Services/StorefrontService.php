<?php

namespace App\Services;

use App\Models\User;
use App\Models\Storefront;
use App\Models\StorefrontProduct;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

class StorefrontService
{
    /**
     * Create a new storefront for a seller
     */
    public function createStorefront(User $user, array $data, ?UploadedFile $logo = null, ?UploadedFile $banner = null): Storefront
    {
        // Check if user already has a storefront
        if ($user->storefront()->exists()) {
            throw new \Exception('You already have a storefront. Please edit your existing one.');
        }

        DB::beginTransaction();

        try {
            // Upload logo
            $logoUrl = null;
            if ($logo) {
                $logoPath = $logo->store('storefronts/logos', 'public');
                $logoUrl = \Storage::disk('public')->url($logoPath);
            }

            // Upload banner
            $bannerUrl = null;
            if ($banner) {
                $bannerPath = $banner->store('storefronts/banners', 'public');
                $bannerUrl = \Storage::disk('public')->url($bannerPath);
            }

            // Create storefront
            $storefront = Storefront::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'logo_url' => $logoUrl,
                'banner_url' => $bannerUrl,
                'primary_color' => $data['primary_color'] ?? '#6366f1',
                'secondary_color' => $data['secondary_color'] ?? '#8b5cf6',
                'accent_color' => $data['accent_color'] ?? '#ec4899',
                'theme' => $data['theme'] ?? 'light',
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? $user->email,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'social_links' => $data['social_links'] ?? null,
                'business_hours' => $data['business_hours'] ?? null,
                'status' => 'active',
            ]);

            AuditService::log(
                'storefront.created',
                "Storefront created: {$storefront->name}",
                $storefront,
                [],
                ['name' => $storefront->name, 'slug' => $storefront->slug],
                ['user_id' => $user->id]
            );

            DB::commit();

            return $storefront;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update storefront
     */
    public function updateStorefront(Storefront $storefront, array $data, ?UploadedFile $logo = null, ?UploadedFile $banner = null): Storefront
    {
        DB::beginTransaction();

        try {
            // Upload new logo if provided
            if ($logo) {
                $logoPath = $logo->store('storefronts/logos', 'public');
                $data['logo_url'] = \Storage::disk('public')->url($logoPath);
            }

            // Upload new banner if provided
            if ($banner) {
                $bannerPath = $banner->store('storefronts/banners', 'public');
                $data['banner_url'] = \Storage::disk('public')->url($bannerPath);
            }

            $storefront->update($data);

            AuditService::log(
                'storefront.updated',
                "Storefront updated: {$storefront->name}",
                $storefront,
                [],
                [],
                ['user_id' => $storefront->user_id]
            );

            DB::commit();

            return $storefront->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Add product to storefront
     */
    public function addProduct(Storefront $storefront, array $data, array $images = []): StorefrontProduct
    {
        DB::beginTransaction();

        try {
            // Upload images
            $imageUrls = [];
            foreach ($images as $image) {
                $imagePath = $image->store("storefronts/{$storefront->id}/products", 'public');
                $imageUrls[] = \Storage::disk('public')->url($imagePath);
            }

            $product = StorefrontProduct::create([
                'storefront_id' => $storefront->id,
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'short_description' => $data['short_description'] ?? null,
                'price' => $data['price'],
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'cost_price' => $data['cost_price'] ?? null,
                'stock_quantity' => $data['stock_quantity'] ?? 0,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? 5,
                'track_inventory' => $data['track_inventory'] ?? true,
                'images' => $imageUrls,
                'variants' => $data['variants'] ?? null,
                'weight' => $data['weight'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
                'is_featured' => $data['is_featured'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $storefront->incrementProducts();

            AuditService::log(
                'storefront.product.added',
                "Product added to storefront: {$product->name}",
                $product,
                [],
                ['product_name' => $product->name, 'sku' => $product->sku],
                ['user_id' => $storefront->user_id, 'storefront_id' => $storefront->id]
            );

            DB::commit();

            return $product;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get storefront by slug or subdomain
     */
    public function getStorefrontByIdentifier(string $identifier): ?Storefront
    {
        return Storefront::where('slug', $identifier)
            ->orWhere('subdomain', $identifier)
            ->with(['products' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('created_at', 'desc');
            }, 'categories'])
            ->first();
    }

    /**
     * Get storefront statistics
     */
    public function getStorefrontStats(Storefront $storefront, int $days = 30): array
    {
        $analytics = $storefront->analytics()
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date', 'desc')
            ->get();

        return [
            'total_products' => $storefront->total_products,
            'total_sales' => $storefront->total_sales,
            'total_revenue' => $storefront->total_revenue,
            'average_rating' => $storefront->average_rating,
            'total_reviews' => $storefront->total_reviews,
            'analytics' => [
                'total_views' => $analytics->sum('page_views'),
                'unique_visitors' => $analytics->sum('unique_visitors'),
                'total_orders' => $analytics->sum('orders'),
                'revenue' => $analytics->sum('revenue'),
                'conversion_rate' => $analytics->avg('conversion_rate'),
                'daily_data' => $analytics,
            ],
        ];
    }
}
