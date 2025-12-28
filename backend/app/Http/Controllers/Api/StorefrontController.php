<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Storefront;
use App\Services\StorefrontService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StorefrontController extends Controller
{
    protected StorefrontService $storefrontService;

    public function __construct(StorefrontService $storefrontService)
    {
        $this->storefrontService = $storefrontService;
    }

    /**
     * Create a new storefront
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme' => 'nullable|in:light,dark,auto',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'banner' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'social_links' => 'nullable|array',
            'business_hours' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $storefront = $this->storefrontService->createStorefront(
                $request->user(),
                $request->all(),
                $request->file('logo'),
                $request->file('banner')
            );

            return response()->json([
                'success' => true,
                'message' => 'Storefront created successfully',
                'data' => $storefront,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create storefront',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user's storefront
     */
    public function getMy(Request $request)
    {
        $storefront = $request->user()->storefront()->first();

        if (!$storefront) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No storefront found',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $storefront->load(['products', 'categories']),
        ]);
    }

    /**
     * Get storefront by slug (public)
     */
    public function show($slug)
    {
        $storefront = $this->storefrontService->getStorefrontByIdentifier($slug);

        if (!$storefront || !$storefront->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Storefront not found or inactive',
            ], 404);
        }

        // Record page view
        \App\Models\StorefrontAnalytics::recordPageView($storefront->id, true);

        return response()->json([
            'success' => true,
            'data' => $storefront,
        ]);
    }

    /**
     * Update storefront
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme' => 'nullable|in:light,dark,auto',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'banner' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
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

            $updated = $this->storefrontService->updateStorefront(
                $storefront,
                $request->all(),
                $request->file('logo'),
                $request->file('banner')
            );

            return response()->json([
                'success' => true,
                'message' => 'Storefront updated successfully',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update storefront',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get storefront statistics
     */
    public function getStats(Request $request)
    {
        try {
            $storefront = $request->user()->storefront()->firstOrFail();
            $days = $request->get('days', 30);

            $stats = $this->storefrontService->getStorefrontStats($storefront, $days);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
