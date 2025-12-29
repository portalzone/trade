<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    protected SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Advanced product search
     */
    public function search(Request $request)
    {
        try {
            $params = [
                'q' => $request->get('q'),
                'category_id' => $request->get('category_id'),
                'min_price' => $request->get('min_price'),
                'max_price' => $request->get('max_price'),
                'min_rating' => $request->get('min_rating'),
                'stock_status' => $request->get('stock_status'),
                'in_stock_only' => $request->boolean('in_stock_only'),
                'featured' => $request->boolean('featured'),
                'storefront_id' => $request->get('storefront_id'),
                'sort' => $request->get('sort', 'relevance'),
                'per_page' => $request->get('per_page', 24),
            ];

            $result = $this->searchService->searchProducts($params);

            return response()->json([
                'success' => true,
                'data' => $result['results'],
                'filters' => [
                    'counts' => $result['filter_counts'],
                    'applied' => $result['applied_filters'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get search suggestions
     */
    public function suggestions(Request $request)
    {
        try {
            $query = $request->get('q', '');
            $limit = min($request->get('limit', 10), 20);

            $suggestions = $this->searchService->getSearchSuggestions($query, $limit);

            return response()->json([
                'success' => true,
                'data' => $suggestions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get suggestions',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get popular searches
     */
    public function popularSearches(Request $request)
    {
        try {
            $limit = min($request->get('limit', 10), 20);
            $searches = $this->searchService->getPopularSearches($limit);

            return response()->json([
                'success' => true,
                'data' => $searches,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get popular searches',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get price range for filtering
     */
    public function priceRange(Request $request)
    {
        try {
            $params = [
                'category_id' => $request->get('category_id'),
            ];

            $range = $this->searchService->getPriceRange($params);

            return response()->json([
                'success' => true,
                'data' => $range,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get price range',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
