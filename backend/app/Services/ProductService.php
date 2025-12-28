<?php

namespace App\Services;

use App\Models\Storefront;
use App\Models\StorefrontProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

class ProductService
{
    /**
     * Create a new product
     */
    public function createProduct(Storefront $storefront, array $data, array $images = []): StorefrontProduct
    {
        DB::beginTransaction();

        try {
            // Upload images
            $imageUrls = [];
            foreach ($images as $image) {
                $imagePath = $image->store("storefronts/{$storefront->id}/products", 'public');
                $imageUrls[] = \Storage::disk('public')->url($imagePath);
            }

            // Create product
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

            // Increment storefront product count
            $storefront->incrementProducts();

            AuditService::log(
                'product.created',
                "Product created: {$product->name}",
                $product,
                [],
                ['product_name' => $product->name, 'sku' => $product->sku],
                ['user_id' => $storefront->user_id, 'storefront_id' => $storefront->id]
            );

            DB::commit();

            return $product->fresh(['category', 'storefront']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update product
     */
    public function updateProduct(StorefrontProduct $product, array $data, array $images = []): StorefrontProduct
    {
        DB::beginTransaction();

        try {
            $oldData = $product->toArray();

            // Upload new images if provided
            if (!empty($images)) {
                $imageUrls = $product->images ?? [];
                foreach ($images as $image) {
                    $imagePath = $image->store("storefronts/{$product->storefront_id}/products", 'public');
                    $imageUrls[] = \Storage::disk('public')->url($imagePath);
                }
                $data['images'] = $imageUrls;
            }

            $product->update(array_filter($data, function ($value) {
                return $value !== null;
            }));

            AuditService::log(
                'product.updated',
                "Product updated: {$product->name}",
                $product,
                $oldData,
                [],
                ['user_id' => $product->storefront->user_id, 'storefront_id' => $product->storefront_id]
            );

            DB::commit();

            return $product->fresh(['category', 'storefront']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete product
     */
    public function deleteProduct(StorefrontProduct $product): bool
    {
        DB::beginTransaction();

        try {
            $storefront = $product->storefront;
            $productName = $product->name;

            $product->delete();

            // Decrement storefront product count
            $storefront->decrementProducts();

            AuditService::log(
                'product.deleted',
                "Product deleted: {$productName}",
                null,
                $product->toArray(),
                [],
                ['user_id' => $storefront->user_id, 'storefront_id' => $storefront->id]
            );

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update product stock
     */
    public function updateStock(StorefrontProduct $product, int $quantity, string $type = 'set'): void
    {
        DB::beginTransaction();

        try {
            $oldQuantity = $product->stock_quantity;

            if ($type === 'increment') {
                $product->increment('stock_quantity', $quantity);
            } elseif ($type === 'decrement') {
                $product->decrement('stock_quantity', $quantity);
            } else {
                $product->update(['stock_quantity' => $quantity]);
            }

            $product->updateStockStatus();
            $product->save();

            AuditService::log(
                'product.stock_updated',
                "Stock updated for {$product->name}: {$oldQuantity} → {$product->stock_quantity}",
                $product,
                ['stock_quantity' => $oldQuantity],
                ['stock_quantity' => $product->stock_quantity],
                ['storefront_id' => $product->storefront_id]
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get low stock products for a storefront
     */
    public function getLowStockProducts(Storefront $storefront)
    {
        return $storefront->products()
            ->where('track_inventory', true)
            ->where('stock_status', 'low_stock')
            ->orderBy('stock_quantity', 'asc')
            ->get();
    }

    /**
     * Get out of stock products
     */
    public function getOutOfStockProducts(Storefront $storefront)
    {
        return $storefront->products()
            ->where('track_inventory', true)
            ->where('stock_status', 'out_of_stock')
            ->get();
    }

    /**
     * Bulk update product status
     */
    public function bulkUpdateStatus(array $productIds, bool $isActive): int
    {
        return StorefrontProduct::whereIn('id', $productIds)
            ->update(['is_active' => $isActive]);
    }

    /**
     * Get product analytics
     */
    public function getProductAnalytics(StorefrontProduct $product): array
    {
        return [
            'views' => $product->views_count,
            'sales' => $product->sales_count,
            'revenue' => $product->sales_count * $product->price,
            'average_rating' => $product->average_rating,
            'reviews_count' => $product->reviews_count,
            'stock_status' => $product->stock_status,
            'discount_percentage' => $product->getDiscountPercentage(),
        ];
    }
}
