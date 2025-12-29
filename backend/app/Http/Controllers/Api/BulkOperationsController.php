<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BulkOperationsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BulkOperationsController extends Controller
{
    protected BulkOperationsService $bulkService;

    public function __construct(BulkOperationsService $bulkService)
    {
        $this->bulkService = $bulkService;
    }

    /**
     * Bulk update products
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'updates' => 'required|array|min:1',
            'updates.*.id' => 'required|exists:storefront_products,id',
            'updates.*.price' => 'nullable|numeric|min:0',
            'updates.*.compare_at_price' => 'nullable|numeric|min:0',
            'updates.*.stock_quantity' => 'nullable|integer|min:0',
            'updates.*.is_active' => 'nullable|boolean',
            'updates.*.is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $storefront = $request->user()->storefront()->firstOrFail();
            $results = $this->bulkService->bulkUpdateProducts(
                $storefront,
                $request->input('updates')
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk update completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk update',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk activate/deactivate products
     */
    public function bulkToggleStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|exists:storefront_products,id',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $storefront = $request->user()->storefront()->firstOrFail();
            $updated = $this->bulkService->bulkToggleStatus(
                $storefront,
                $request->input('product_ids'),
                $request->boolean('is_active')
            );

            return response()->json([
                'success' => true,
                'message' => "{$updated} products " . ($request->boolean('is_active') ? 'activated' : 'deactivated'),
                'data' => ['count' => $updated],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk price adjustment
     */
    public function bulkPriceAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|exists:storefront_products,id',
            'type' => 'required|in:percentage_increase,percentage_decrease,fixed_increase,fixed_decrease',
            'value' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $storefront = $request->user()->storefront()->firstOrFail();
            $updated = $this->bulkService->bulkPriceAdjustment(
                $storefront,
                $request->input('product_ids'),
                $request->input('type'),
                $request->input('value')
            );

            return response()->json([
                'success' => true,
                'message' => "{$updated} products updated",
                'data' => ['count' => $updated],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust prices',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Export products to CSV
     */
    public function exportCSV(Request $request)
    {
        try {
            $storefront = $request->user()->storefront()->firstOrFail();
            $csv = $this->bulkService->exportToCSV($storefront);

            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="products_' . date('Y-m-d') . '.csv"');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export CSV',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Import products from CSV
     */
    public function importCSV(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $storefront = $request->user()->storefront()->firstOrFail();
            $csvContent = file_get_contents($request->file('file')->getRealPath());

            $results = $this->bulkService->importFromCSV($storefront, $csvContent);

            return response()->json([
                'success' => true,
                'message' => 'Import completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import CSV',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts(Request $request)
    {
        try {
            $storefront = $request->user()->storefront()->firstOrFail();
            $products = $this->bulkService->getLowStockAlerts($storefront);

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get alerts',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get out of stock products
     */
    public function getOutOfStock(Request $request)
    {
        try {
            $storefront = $request->user()->storefront()->firstOrFail();
            $products = $this->bulkService->getOutOfStockProducts($storefront);

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get out of stock products',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk delete products
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|exists:storefront_products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $storefront = $request->user()->storefront()->firstOrFail();
            $deleted = $this->bulkService->bulkDeleteProducts(
                $storefront,
                $request->input('product_ids')
            );

            return response()->json([
                'success' => true,
                'message' => "{$deleted} products deleted",
                'data' => ['count' => $deleted],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete products',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
