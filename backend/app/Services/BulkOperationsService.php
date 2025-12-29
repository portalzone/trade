<?php

namespace App\Services;

use App\Models\StorefrontProduct;
use App\Models\Storefront;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BulkOperationsService
{
    /**
     * Bulk update products
     */
    public function bulkUpdateProducts(Storefront $storefront, array $updates): array
    {
        DB::beginTransaction();

        try {
            $results = [
                'updated' => 0,
                'failed' => 0,
                'errors' => [],
            ];

            foreach ($updates as $update) {
                try {
                    $product = $storefront->products()->findOrFail($update['id']);
                    
                    $updateData = array_filter([
                        'price' => $update['price'] ?? null,
                        'compare_at_price' => $update['compare_at_price'] ?? null,
                        'stock_quantity' => $update['stock_quantity'] ?? null,
                        'is_active' => $update['is_active'] ?? null,
                        'is_featured' => $update['is_featured'] ?? null,
                    ], function ($value) {
                        return $value !== null;
                    });

                    $product->update($updateData);
                    $results['updated']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'id' => $update['id'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            AuditService::log(
                'products.bulk_update',
                "Bulk updated {$results['updated']} products",
                null,
                [],
                ['updated' => $results['updated'], 'failed' => $results['failed']],
                ['storefront_id' => $storefront->id]
            );

            DB::commit();

            return $results;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Bulk activate/deactivate products
     */
    public function bulkToggleStatus(Storefront $storefront, array $productIds, bool $isActive): int
    {
        DB::beginTransaction();

        try {
            $updated = $storefront->products()
                ->whereIn('id', $productIds)
                ->update([
                    'is_active' => $isActive,
                    'published_at' => $isActive ? now() : null,
                ]);

            AuditService::log(
                'products.bulk_status_change',
                "Bulk " . ($isActive ? 'activated' : 'deactivated') . " {$updated} products",
                null,
                [],
                ['count' => $updated, 'status' => $isActive],
                ['storefront_id' => $storefront->id]
            );

            DB::commit();

            return $updated;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Bulk price adjustment
     */
    public function bulkPriceAdjustment(
        Storefront $storefront,
        array $productIds,
        string $type,
        float $value
    ): int {
        DB::beginTransaction();

        try {
            $products = $storefront->products()->whereIn('id', $productIds)->get();
            $updated = 0;

            foreach ($products as $product) {
                $newPrice = $product->price;

                if ($type === 'percentage_increase') {
                    $newPrice = $product->price * (1 + ($value / 100));
                } elseif ($type === 'percentage_decrease') {
                    $newPrice = $product->price * (1 - ($value / 100));
                } elseif ($type === 'fixed_increase') {
                    $newPrice = $product->price + $value;
                } elseif ($type === 'fixed_decrease') {
                    $newPrice = max(0, $product->price - $value);
                }

                $product->update(['price' => round($newPrice, 2)]);
                $updated++;
            }

            AuditService::log(
                'products.bulk_price_adjustment',
                "Bulk price adjustment on {$updated} products",
                null,
                [],
                ['count' => $updated, 'type' => $type, 'value' => $value],
                ['storefront_id' => $storefront->id]
            );

            DB::commit();

            return $updated;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Export products to CSV
     */
    public function exportToCSV(Storefront $storefront): string
    {
        $products = $storefront->products()->with('category')->get();

        $csv = "ID,SKU,Name,Category,Price,Compare Price,Stock,Status,Featured,Created\n";

        foreach ($products as $product) {
            $csv .= implode(',', [
                $product->id,
                $product->sku,
                '"' . str_replace('"', '""', $product->name) . '"',
                '"' . ($product->category?->name ?? 'N/A') . '"',
                $product->price,
                $product->compare_at_price ?? '',
                $product->stock_quantity,
                $product->is_active ? 'Active' : 'Inactive',
                $product->is_featured ? 'Yes' : 'No',
                $product->created_at->format('Y-m-d'),
            ]) . "\n";
        }

        return $csv;
    }

    /**
     * Import products from CSV
     */
    public function importFromCSV(Storefront $storefront, string $csvContent): array
    {
        DB::beginTransaction();

        try {
            $results = [
                'imported' => 0,
                'updated' => 0,
                'failed' => 0,
                'errors' => [],
            ];

            $lines = explode("\n", $csvContent);
            $headers = str_getcsv(array_shift($lines));

            foreach ($lines as $index => $line) {
                if (empty(trim($line))) continue;

                try {
                    $data = str_getcsv($line);
                    $row = array_combine($headers, $data);

                    // Check if product exists by SKU
                    $product = $storefront->products()->where('sku', $row['SKU'])->first();

                    $productData = [
                        'name' => $row['Name'],
                        'price' => $row['Price'],
                        'compare_at_price' => $row['Compare Price'] ?? null,
                        'stock_quantity' => $row['Stock'] ?? 0,
                        'is_active' => strtolower($row['Status']) === 'active',
                        'is_featured' => strtolower($row['Featured'] ?? 'no') === 'yes',
                    ];

                    if ($product) {
                        $product->update($productData);
                        $results['updated']++;
                    } else {
                        $productData['sku'] = $row['SKU'];
                        $productData['storefront_id'] = $storefront->id;
                        StorefrontProduct::create($productData);
                        $results['imported']++;
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'row' => $index + 2,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            AuditService::log(
                'products.csv_import',
                "CSV import: {$results['imported']} imported, {$results['updated']} updated",
                null,
                [],
                $results,
                ['storefront_id' => $storefront->id]
            );

            DB::commit();

            return $results;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts(Storefront $storefront)
    {
        return $storefront->products()
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->where('stock_status', 'low_stock')
            ->with('category')
            ->orderBy('stock_quantity', 'asc')
            ->get();
    }

    /**
     * Get out of stock products
     */
    public function getOutOfStockProducts(Storefront $storefront)
    {
        return $storefront->products()
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->where('stock_status', 'out_of_stock')
            ->with('category')
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    /**
     * Bulk delete products
     */
    public function bulkDeleteProducts(Storefront $storefront, array $productIds): int
    {
        DB::beginTransaction();

        try {
            $deleted = $storefront->products()
                ->whereIn('id', $productIds)
                ->delete();

            // Update storefront product count
            $storefront->decrement('total_products', $deleted);

            AuditService::log(
                'products.bulk_delete',
                "Bulk deleted {$deleted} products",
                null,
                [],
                ['count' => $deleted],
                ['storefront_id' => $storefront->id]
            );

            DB::commit();

            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
