<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BusinessVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessVerificationController extends Controller
{
    protected BusinessVerificationService $businessService;

    public function __construct(BusinessVerificationService $businessService)
    {
        $this->businessService = $businessService;
    }

    /**
     * Submit Tier 2 business verification
     */
    public function submitTier2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'registration_number' => 'required|string|max:100|unique:business_verifications',
            'cac_number' => 'nullable|string|max:100',
            'registration_date' => 'nullable|date',
            'business_address' => 'required|string',
            'business_phone' => 'nullable|string|max:20',
            'business_email' => 'nullable|email',
            'business_type' => 'required|in:sole_proprietorship,limited_liability,partnership,enterprise',
            'cac_certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'tin_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'directors' => 'nullable|array',
            'directors.*.full_name' => 'required|string',
            'directors.*.nin' => 'nullable|string|size:11',
            'directors.*.bvn' => 'nullable|string|size:11',
            'directors.*.phone' => 'nullable|string',
            'directors.*.email' => 'nullable|email',
            'directors.*.role' => 'nullable|in:director,shareholder,beneficial_owner,secretary',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $files = [
                'cac_certificate' => $request->file('cac_certificate'),
                'tin_certificate' => $request->file('tin_certificate'),
            ];

            $verification = $this->businessService->submitTier2Verification(
                $request->user(),
                $request->all(),
                $files
            );

            return response()->json([
                'success' => true,
                'message' => 'Business verification submitted successfully',
                'data' => $verification,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit business verification',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user's business verification status
     */
    public function getStatus(Request $request)
    {
        $verification = $this->businessService->getUserVerification($request->user());

        if (!$verification) {
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'not_submitted',
                    'tier' => $request->user()->kyc_tier,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $verification,
        ]);
    }
}
