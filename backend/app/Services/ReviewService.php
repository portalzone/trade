<?php

namespace App\Services;

use App\Models\ProductReview;
use App\Models\ReviewVote;
use App\Models\StorefrontProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

class ReviewService
{
    /**
     * Create a product review
     */
    public function createReview(User $user, StorefrontProduct $product, array $data, array $images = []): ProductReview
    {
        DB::beginTransaction();

        try {
            // Upload review images
            $imageUrls = [];
            foreach ($images as $image) {
                $imagePath = $image->store("reviews/products/{$product->id}", 'public');
                $imageUrls[] = \Storage::disk('public')->url($imagePath);
            }

            // Check if user has purchased this product
            $isVerifiedPurchase = false;
            if (isset($data['order_id'])) {
                $order = \App\Models\Order::where('id', $data['order_id'])
                    ->where('buyer_id', $user->id)
                    ->where('status', 'COMPLETED')
                    ->first();
                
                if ($order) {
                    $isVerifiedPurchase = true;
                }
            }

            $review = ProductReview::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'order_id' => $data['order_id'] ?? null,
                'rating' => $data['rating'],
                'title' => $data['title'] ?? null,
                'comment' => $data['comment'] ?? null,
                'images' => $imageUrls,
                'is_verified_purchase' => $isVerifiedPurchase,
                'is_approved' => true, // Auto-approve for now
                'approved_at' => now(),
            ]);

            // Recalculate product rating
            $product->recalculateRating();

            AuditService::log(
                'review.created',
                "Review created for product: {$product->name}",
                $review,
                [],
                ['rating' => $review->rating, 'product_id' => $product->id],
                ['user_id' => $user->id]
            );

            DB::commit();

            return $review->fresh(['user', 'product']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update review
     */
    public function updateReview(ProductReview $review, array $data, array $images = []): ProductReview
    {
        DB::beginTransaction();

        try {
            $oldData = $review->toArray();

            // Upload new images if provided
            if (!empty($images)) {
                $imageUrls = $review->images ?? [];
                foreach ($images as $image) {
                    $imagePath = $image->store("reviews/products/{$review->product_id}", 'public');
                    $imageUrls[] = \Storage::disk('public')->url($imagePath);
                }
                $data['images'] = $imageUrls;
            }

            $review->update(array_filter($data, function ($value) {
                return $value !== null;
            }));

            // Recalculate product rating if rating changed
            if (isset($data['rating']) && $data['rating'] != $oldData['rating']) {
                $review->product->recalculateRating();
            }

            AuditService::log(
                'review.updated',
                "Review updated for product: {$review->product->name}",
                $review,
                $oldData,
                [],
                ['user_id' => $review->user_id]
            );

            DB::commit();

            return $review->fresh(['user', 'product']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete review
     */
    public function deleteReview(ProductReview $review): bool
    {
        DB::beginTransaction();

        try {
            $productId = $review->product_id;
            $product = $review->product;

            $review->delete();

            // Recalculate product rating
            $product->recalculateRating();

            AuditService::log(
                'review.deleted',
                "Review deleted for product: {$product->name}",
                null,
                $review->toArray(),
                [],
                ['user_id' => $review->user_id]
            );

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Add seller response
     */
    public function addSellerResponse(ProductReview $review, string $response): ProductReview
    {
        DB::beginTransaction();

        try {
            $review->update([
                'seller_response' => $response,
                'seller_responded_at' => now(),
            ]);

            AuditService::log(
                'review.seller_response',
                "Seller responded to review",
                $review,
                [],
                [],
                ['product_id' => $review->product_id]
            );

            DB::commit();

            return $review->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Vote on review helpfulness
     */
    public function voteReview(User $user, ProductReview $review, bool $isHelpful): ReviewVote
    {
        DB::beginTransaction();

        try {
            // Check if user already voted
            $existingVote = ReviewVote::where('review_id', $review->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingVote) {
                // Update existing vote
                if ($existingVote->is_helpful !== $isHelpful) {
                    // Reverse previous vote
                    if ($existingVote->is_helpful) {
                        $review->decrementHelpful();
                    } else {
                        $review->decrementNotHelpful();
                    }

                    // Apply new vote
                    if ($isHelpful) {
                        $review->incrementHelpful();
                    } else {
                        $review->incrementNotHelpful();
                    }

                    $existingVote->update(['is_helpful' => $isHelpful]);
                }

                DB::commit();
                return $existingVote;
            }

            // Create new vote
            $vote = ReviewVote::create([
                'review_id' => $review->id,
                'user_id' => $user->id,
                'is_helpful' => $isHelpful,
            ]);

            // Update counts
            if ($isHelpful) {
                $review->incrementHelpful();
            } else {
                $review->incrementNotHelpful();
            }

            DB::commit();

            return $vote;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get product rating breakdown
     */
    public function getRatingBreakdown(StorefrontProduct $product): array
    {
        $reviews = $product->reviews()
            ->where('is_approved', true)
            ->get();

        $breakdown = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0,
        ];

        foreach ($reviews as $review) {
            $breakdown[$review->rating]++;
        }

        $total = $reviews->count();

        return [
            'average_rating' => $product->average_rating,
            'total_reviews' => $total,
            'breakdown' => array_map(function ($count) use ($total) {
                return [
                    'count' => $count,
                    'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                ];
            }, $breakdown),
        ];
    }
}
