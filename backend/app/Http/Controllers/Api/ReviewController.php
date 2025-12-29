<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\StorefrontProduct;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    protected ReviewService $reviewService;

    public function __construct(ReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    /**
     * Create a review
     */
    public function create(Request $request, $productId)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'order_id' => 'nullable|exists:orders,id',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $product = StorefrontProduct::findOrFail($productId);

            // Check if user already reviewed this product
            $existingReview = ProductReview::where('product_id', $product->id)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this product',
                ], 400);
            }

            $review = $this->reviewService->createReview(
                $request->user(),
                $product,
                $request->all(),
                $request->file('images', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Review created successfully',
                'data' => $review,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create review',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get product reviews
     */
    public function getProductReviews($productId, Request $request)
    {
        try {
            $product = StorefrontProduct::findOrFail($productId);

            $reviews = $product->approvedReviews()
                ->with(['user:id,full_name,profile_photo_url'])
                ->when($request->get('rating'), function ($query, $rating) {
                    $query->where('rating', $rating);
                })
                ->when($request->get('verified_only'), function ($query) {
                    $query->where('is_verified_purchase', true);
                })
                ->when($request->get('sort'), function ($query, $sort) {
                    if ($sort === 'helpful') {
                        $query->orderBy('helpful_count', 'desc');
                    } elseif ($sort === 'rating_high') {
                        $query->orderBy('rating', 'desc');
                    } elseif ($sort === 'rating_low') {
                        $query->orderBy('rating', 'asc');
                    } else {
                        $query->orderBy('created_at', 'desc');
                    }
                }, function ($query) {
                    $query->orderBy('created_at', 'desc');
                })
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $reviews,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }
    }

    /**
     * Get rating breakdown
     */
    public function getRatingBreakdown($productId)
    {
        try {
            $product = StorefrontProduct::findOrFail($productId);
            $breakdown = $this->reviewService->getRatingBreakdown($product);

            return response()->json([
                'success' => true,
                'data' => $breakdown,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }
    }

    /**
     * Update review
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'nullable|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $review = ProductReview::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $updated = $this->reviewService->updateReview(
                $review,
                $request->all(),
                $request->file('images', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete review
     */
    public function delete(Request $request, $id)
    {
        try {
            $review = ProductReview::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $this->reviewService->deleteReview($review);

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Vote on review helpfulness
     */
    public function vote(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'is_helpful' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $review = ProductReview::findOrFail($id);

            $vote = $this->reviewService->voteReview(
                $request->user(),
                $review,
                $request->boolean('is_helpful')
            );

            return response()->json([
                'success' => true,
                'message' => 'Vote recorded successfully',
                'data' => $vote,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record vote',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Add seller response
     */
    public function addSellerResponse(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'response' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $review = ProductReview::findOrFail($id);

            // Verify user owns the storefront
            $storefront = $request->user()->storefront()->firstOrFail();
            
            if ($review->product->storefront_id !== $storefront->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $updated = $this->reviewService->addSellerResponse(
                $review,
                $request->input('response')
            );

            return response()->json([
                'success' => true,
                'message' => 'Response added successfully',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add response',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user's reviews
     */
    public function getMyReviews(Request $request)
    {
        $reviews = ProductReview::where('user_id', $request->user()->id)
            ->with(['product:id,name,slug,price,images'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }
}
