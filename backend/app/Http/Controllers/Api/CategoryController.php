<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    protected CategoryService $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    /**
     * Create a new category
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:product_categories,id',
            'icon' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
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

            $category = $this->categoryService->createCategory(
                $storefront,
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => $category,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all categories for seller's storefront
     */
    public function getMyCategories(Request $request)
    {
        try {
            $storefront = $request->user()->storefront()->firstOrFail();

            $categories = $storefront->categories()
                ->with(['parent', 'children'])
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            // Build tree structure
            $tree = $this->categoryService->buildCategoryTree($categories);

            return response()->json([
                'success' => true,
                'data' => [
                    'flat' => $categories,
                    'tree' => $tree,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get single category
     */
    public function show($id)
    {
        try {
            $category = ProductCategory::with(['parent', 'children', 'products'])
                ->withCount('products')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $category,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }
    }

    /**
     * Update category
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:product_categories,id',
            'icon' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
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
            $category = $storefront->categories()->findOrFail($id);

            $updated = $this->categoryService->updateCategory(
                $category,
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete category
     */
    public function delete(Request $request, $id)
    {
        try {
            $storefront = $request->user()->storefront()->firstOrFail();
            $category = $storefront->categories()->findOrFail($id);

            $this->categoryService->deleteCategory($category);

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get categories for a public storefront
     */
    public function getStorefrontCategories($slug)
    {
        try {
            $storefront = \App\Models\Storefront::where('slug', $slug)
                ->orWhere('subdomain', $slug)
                ->firstOrFail();

            $categories = $storefront->categories()
                ->where('is_active', true)
                ->with('children')
                ->withCount('products')
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Storefront not found',
            ], 404);
        }
    }
}
