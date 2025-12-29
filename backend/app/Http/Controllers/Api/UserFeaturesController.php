<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserFeaturesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserFeaturesController extends Controller
{
    protected UserFeaturesService $userFeaturesService;

    public function __construct(UserFeaturesService $userFeaturesService)
    {
        $this->userFeaturesService = $userFeaturesService;
    }

    /**
     * Add product to wishlist
     */
    public function addToWishlist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:storefront_products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $wishlist = $this->userFeaturesService->addToWishlist(
                $request->user(),
                $request->input('product_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Product added to wishlist',
                'data' => $wishlist,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add to wishlist',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove product from wishlist
     */
    public function removeFromWishlist(Request $request, $productId)
    {
        try {
            $removed = $this->userFeaturesService->removeFromWishlist(
                $request->user(),
                $productId
            );

            if ($removed) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product removed from wishlist',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Product not found in wishlist',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove from wishlist',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user's wishlist
     */
    public function getWishlist(Request $request)
    {
        try {
            $products = $this->userFeaturesService->getWishlist($request->user());

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get wishlist',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Check if product is in wishlist
     */
    public function checkWishlist(Request $request, $productId)
    {
        try {
            $inWishlist = $this->userFeaturesService->isInWishlist(
                $request->user(),
                $productId
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'in_wishlist' => $inWishlist,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check wishlist',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get recently viewed products
     */
    public function getRecentlyViewed(Request $request)
    {
        try {
            $user = $request->user();
            $sessionId = $request->header('X-Session-Id');

            $products = $this->userFeaturesService->getRecentlyViewed(
                $user,
                $sessionId,
                $request->get('limit', 20)
            );

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recently viewed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get best sellers
     */
    public function getBestSellers(Request $request)
    {
        try {
            $products = $this->userFeaturesService->getBestSellers(
                $request->get('limit', 10),
                $request->get('days', 30)
            );

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get best sellers',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get trending products
     */
    public function getTrending(Request $request)
    {
        try {
            $products = $this->userFeaturesService->getTrendingProducts(
                $request->get('limit', 10),
                $request->get('hours', 24)
            );

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get trending products',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get top rated products
     */
    public function getTopRated(Request $request)
    {
        try {
            $products = $this->userFeaturesService->getTopRated(
                $request->get('limit', 10),
                $request->get('min_rating', 4.0)
            );

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get top rated products',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Compare products
     */
    public function compareProducts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array|min:2|max:4',
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
            $comparison = $this->userFeaturesService->compareProducts(
                $request->input('product_ids')
            );

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to compare products',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
