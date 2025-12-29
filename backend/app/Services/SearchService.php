<?php

namespace App\Services;

use App\Models\StorefrontProduct;
use Illuminate\Database\Eloquent\Builder;

class SearchService
{
    /**
     * Advanced product search with filters
     */
    public function searchProducts(array $params): array
    {
        $query = StorefrontProduct::query()
            ->with(['category', 'storefront'])
            ->where('is_active', true);

        // Text search
        if (!empty($params['q'])) {
            $searchTerm = $params['q'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ILIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'ILIKE', "%{$searchTerm}%")
                    ->orWhere('short_description', 'ILIKE', "%{$searchTerm}%")
                    ->orWhere('sku', 'ILIKE', "%{$searchTerm}%");
            });
        }

        // Category filter
        if (!empty($params['category_id'])) {
            $query->where('category_id', $params['category_id']);
        }

        // Price range filter
        if (isset($params['min_price'])) {
            $query->where('price', '>=', $params['min_price']);
        }

        if (isset($params['max_price'])) {
            $query->where('price', '<=', $params['max_price']);
        }

        // Rating filter
        if (isset($params['min_rating'])) {
            $query->where('average_rating', '>=', $params['min_rating']);
        }

        // Stock status filter
        if (!empty($params['stock_status'])) {
            $query->where('stock_status', $params['stock_status']);
        }

        // In stock only
        if (!empty($params['in_stock_only'])) {
            $query->where('stock_status', 'in_stock');
        }

        // Featured products only
        if (!empty($params['featured'])) {
            $query->where('is_featured', true);
        }

        // Storefront filter
        if (!empty($params['storefront_id'])) {
            $query->where('storefront_id', $params['storefront_id']);
        }

        // Sorting
        $this->applySorting($query, $params['sort'] ?? 'relevance', $params['q'] ?? null);

        // Pagination
        $perPage = min($params['per_page'] ?? 24, 100);
        $results = $query->paginate($perPage);

        // Get filter counts
        $filterCounts = $this->getFilterCounts($params);

        return [
            'results' => $results,
            'filter_counts' => $filterCounts,
            'applied_filters' => $this->getAppliedFilters($params),
        ];
    }

    /**
     * Apply sorting to query
     */
    protected function applySorting(Builder $query, string $sort, ?string $searchTerm): void
    {
        switch ($sort) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;

            case 'price_high':
                $query->orderBy('price', 'desc');
                break;

            case 'rating':
                $query->orderBy('average_rating', 'desc')
                    ->orderBy('reviews_count', 'desc');
                break;

            case 'popular':
                $query->orderBy('sales_count', 'desc')
                    ->orderBy('views_count', 'desc');
                break;

            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;

            case 'relevance':
            default:
                if ($searchTerm) {
                    // Simple relevance scoring
                    $query->orderByRaw("
                        CASE 
                            WHEN name ILIKE ? THEN 1
                            WHEN name ILIKE ? THEN 2
                            WHEN description ILIKE ? THEN 3
                            ELSE 4
                        END
                    ", [$searchTerm, "%{$searchTerm}%", "%{$searchTerm}%"])
                        ->orderBy('sales_count', 'desc');
                } else {
                    $query->orderBy('sales_count', 'desc')
                        ->orderBy('created_at', 'desc');
                }
                break;
        }
    }

    /**
     * Get filter counts for faceted search
     */
    protected function getFilterCounts(array $params): array
    {
        $baseQuery = StorefrontProduct::query()->where('is_active', true);

        // Apply search term if exists
        if (!empty($params['q'])) {
            $searchTerm = $params['q'];
            $baseQuery->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ILIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'ILIKE', "%{$searchTerm}%")
                    ->orWhere('short_description', 'ILIKE', "%{$searchTerm}%");
            });
        }

        return [
            'stock_status' => [
                'in_stock' => (clone $baseQuery)->where('stock_status', 'in_stock')->count(),
                'low_stock' => (clone $baseQuery)->where('stock_status', 'low_stock')->count(),
                'out_of_stock' => (clone $baseQuery)->where('stock_status', 'out_of_stock')->count(),
            ],
            'rating' => [
                '5' => (clone $baseQuery)->where('average_rating', '>=', 5)->count(),
                '4' => (clone $baseQuery)->where('average_rating', '>=', 4)->where('average_rating', '<', 5)->count(),
                '3' => (clone $baseQuery)->where('average_rating', '>=', 3)->where('average_rating', '<', 4)->count(),
                '2' => (clone $baseQuery)->where('average_rating', '>=', 2)->where('average_rating', '<', 3)->count(),
                '1' => (clone $baseQuery)->where('average_rating', '>=', 1)->where('average_rating', '<', 2)->count(),
            ],
            'price_ranges' => [
                'under_100k' => (clone $baseQuery)->where('price', '<', 100000)->count(),
                '100k_500k' => (clone $baseQuery)->whereBetween('price', [100000, 500000])->count(),
                '500k_1m' => (clone $baseQuery)->whereBetween('price', [500000, 1000000])->count(),
                'over_1m' => (clone $baseQuery)->where('price', '>', 1000000)->count(),
            ],
        ];
    }

    /**
     * Get applied filters summary
     */
    protected function getAppliedFilters(array $params): array
    {
        $applied = [];

        if (!empty($params['q'])) {
            $applied['search'] = $params['q'];
        }

        if (!empty($params['category_id'])) {
            $applied['category_id'] = $params['category_id'];
        }

        if (isset($params['min_price']) || isset($params['max_price'])) {
            $applied['price_range'] = [
                'min' => $params['min_price'] ?? null,
                'max' => $params['max_price'] ?? null,
            ];
        }

        if (isset($params['min_rating'])) {
            $applied['min_rating'] = $params['min_rating'];
        }

        if (!empty($params['stock_status'])) {
            $applied['stock_status'] = $params['stock_status'];
        }

        if (!empty($params['featured'])) {
            $applied['featured'] = true;
        }

        return $applied;
    }

    /**
     * Get search suggestions
     */
    public function getSearchSuggestions(string $query, int $limit = 10): array
    {
        if (strlen($query) < 2) {
            return [];
        }

        $products = StorefrontProduct::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                    ->orWhere('description', 'ILIKE', "%{$query}%");
            })
            ->select(['id', 'name', 'slug', 'price', 'images'])
            ->limit($limit)
            ->get();

        return $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'image' => $product->images[0] ?? null,
            ];
        })->toArray();
    }

    /**
     * Get popular search terms (placeholder for future implementation)
     */
    public function getPopularSearches(int $limit = 10): array
    {
        // This would typically come from a search_logs table
        // For now, return empty array
        return [];
    }

    /**
     * Get price range for products
     */
    public function getPriceRange(array $params = []): array
    {
        $query = StorefrontProduct::query()->where('is_active', true);

        if (!empty($params['category_id'])) {
            $query->where('category_id', $params['category_id']);
        }

        return [
            'min' => (float) $query->min('price'),
            'max' => (float) $query->max('price'),
        ];
    }
}
