<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessVerification;
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

    public function index(Request $request)
    {
        $query = BusinessVerification::with(['user:id,full_name,email', 'directors', 'reviewer:id,full_name'])
            ->orderBy('submitted_at', 'desc');

        if ($request->has('status')) {
            $query->where('verification_status', $request->status);
        }

        if ($request->has('tier')) {
            $query->where('tier', $request->tier);
        }

        if ($request->boolean('pending')) {
            $query->whereIn('verification_status', ['pending', 'under_review']);
        }

        $verifications = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $verifications,
        ]);
    }

    public function show($id)
    {
        try {
            $verification = BusinessVerification::with([
                'user:id,full_name,email,phone_number,kyc_tier',
                'directors',
                'reviewer:id,full_name'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $verification,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function approve(Request $request, $id)
    {
        try {
            $verification = BusinessVerification::findOrFail($id);

            if ($verification->verification_status === 'verified') {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification already approved',
                ], 400);
            }

            $approved = $this->businessService->approveVerification($verification, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Business verification approved successfully',
                'data' => $approved->load(['user', 'directors']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve verification',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $verification = BusinessVerification::findOrFail($id);

            if ($verification->verification_status === 'verified') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot reject verified verification',
                ], 400);
            }

            $rejected = $this->businessService->rejectVerification(
                $verification,
                $request->user(),
                $request->reason
            );

            return response()->json([
                'success' => true,
                'message' => 'Business verification rejected',
                'data' => $rejected->load(['user', 'directors']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject verification',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function requestInfo(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $verification = BusinessVerification::findOrFail($id);

            $currentNotes = $verification->verification_notes ?? [];
            $currentNotes[] = [
                'admin_id' => $request->user()->id,
                'admin_name' => $request->user()->full_name,
                'note' => $request->notes,
                'created_at' => now()->toIso8601String(),
            ];

            $verification->update([
                'verification_status' => 'requires_additional_info',
                'verification_notes' => $currentNotes,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Additional information requested',
                'data' => $verification->fresh()->load(['user', 'directors']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to request additional information',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function statistics()
    {
        $stats = [
            'total' => BusinessVerification::count(),
            'pending' => BusinessVerification::where('verification_status', 'pending')->count(),
            'under_review' => BusinessVerification::where('verification_status', 'under_review')->count(),
            'verified' => BusinessVerification::where('verification_status', 'verified')->count(),
            'rejected' => BusinessVerification::where('verification_status', 'rejected')->count(),
            'requires_info' => BusinessVerification::where('verification_status', 'requires_additional_info')->count(),
            'tier2' => BusinessVerification::where('tier', 'tier2')->count(),
            'tier3' => BusinessVerification::where('tier', 'tier3')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
