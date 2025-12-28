<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StorefrontProduct;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Add product to storefront
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'track_inventory' => 'nullable|boolean',
            'weight' => 'nullable|string',
            'dimensions' => 'nullable|array',
            'dimensions.length' => 'nullable|numeric',
            'dimensions.width' => 'nullable|numeric',
            'dimensions.height' => 'nullable|numeric',
            'variants' => 'nullable|array',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
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

            $product = $this->productService->createProduct(
                $storefront,
                $request->all(),
                $request->file('images', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all products for seller's storefront
     */
    public function getMyProducts(Request $request)
    {
        try {
            $storefront = $request->user()->storefront()->firstOrFail();

            $products = $storefront->products()
                ->with('category')
                ->when($request->get('status'), function ($query, $status) {
                    if ($status === 'active') {
                        $query->where('is_active', true);
                    } elseif ($status === 'inactive') {
                        $query->where('is_active', false);
                    }
                })
                ->when($request->get('stock_status'), function ($query, $stockStatus) {
                    $query->where('stock_status', $stockStatus);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve products',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get single product
     */
    public function show($id)
    {
        try {
            $product = StorefrontProduct::with(['category', 'storefront'])
                ->findOrFail($id);

            // Increment views
            $product->incrementViews();

            return response()->json([
                'success' => true,
                'data' => $product,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }
    }

    /**
     * Update product
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:product_categories,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'nullable|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
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
            $product = $storefront->products()->findOrFail($id);

            $updated = $this->productService->updateProduct(
                $product,
                $request->all(),
                $request->file('images', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete product
     */
    public function delete(Request $request, $id)
    {
        try {
            $storefront = $request->user()->storefront()->firstOrFail();
            $product = $storefront->products()->findOrFail($id);

            $this->productService->deleteProduct($product);

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get products for a public storefront
     */
    public function getStorefrontProducts($slug, Request $request)
    {
        try {
            $storefront = \App\Models\Storefront::where('slug', $slug)
                ->orWhere('subdomain', $slug)
                ->firstOrFail();

            $products = $storefront->products()
                ->where('is_active', true)
                ->with('category')
                ->when($request->get('category'), function ($query, $categoryId) {
                    $query->where('category_id', $categoryId);
                })
                ->when($request->get('featured'), function ($query) {
                    $query->where('is_featured', true);
                })
                ->when($request->get('min_price'), function ($query, $minPrice) {
                    $query->where('price', '>=', $minPrice);
                })
                ->when($request->get('max_price'), function ($query, $maxPrice) {
                    $query->where('price', '<=', $maxPrice);
                })
                ->when($request->get('sort'), function ($query, $sort) {
                    if ($sort === 'price_low') {
                        $query->orderBy('price', 'asc');
                    } elseif ($sort === 'price_high') {
                        $query->orderBy('price', 'desc');
                    } elseif ($sort === 'popular') {
                        $query->orderBy('sales_count', 'desc');
                    } elseif ($sort === 'rating') {
                        $query->orderBy('average_rating', 'desc');
                    } else {
                        $query->orderBy('created_at', 'desc');
                    }
                }, function ($query) {
                    $query->orderBy('created_at', 'desc');
                })
                ->paginate($request->get('per_page', 24));

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Storefront not found',
            ], 404);
        }
    }
}
