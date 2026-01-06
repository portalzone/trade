<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessVerification;
use App\Services\BusinessVerificationService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessVerificationController extends Controller
{
    protected BusinessVerificationService $businessService;
    protected NotificationService $notificationService;

    public function __construct(
        BusinessVerificationService $businessService,
        NotificationService $notificationService
    ) {
        $this->businessService = $businessService;
        $this->notificationService = $notificationService;
    }

    /**
     * List all business verifications with filters
     */
    public function index(Request $request)
    {
        $query = BusinessVerification::with(['user:id,full_name,email', 'directors', 'reviewer:id,full_name'])
            ->orderBy('submitted_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('verification_status', $request->status);
        }

        // Filter by tier
        if ($request->has('tier')) {
            $query->where('tier', $request->tier);
        }

        // Pending only
        if ($request->boolean('pending')) {
            $query->whereIn('verification_status', ['pending', 'under_review']);
        }

        $verifications = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $verifications,
        ]);
    }

    /**
     * Get verification details
     */
    public function show($id)
    {
        try {
            $verification = BusinessVerification::with([
                'user:id,full_name,email,phone,kyc_tier',
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

    /**
     * Approve business verification
     */
    public function approve(Request $request, $id)
    {
        try {
            $verification = BusinessVerification::with('user')->findOrFail($id);

            if ($verification->verification_status === 'verified') {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification already approved',
                ], 400);
            }

            $approved = $this->businessService->approveVerification($verification, $request->user());

            // Send email notification
            $this->notificationService->sendKycApproved($verification->user, 2);

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

    /**
     * Reject business verification
     */
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
            $verification = BusinessVerification::with('user')->findOrFail($id);

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

            // Send email notification
            $this->notificationService->sendKycRejected($verification->user, 2, $request->reason);

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

    /**
     * Request additional information
     */
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

            // Add notes to verification_notes array
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

    /**
     * Get verification statistics
     */
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
            'recent_submissions' => BusinessVerification::where('submitted_at', '>=', now()->subDays(7))->count(),
            'avg_review_time_hours' => BusinessVerification::whereNotNull('reviewed_at')
                ->whereNotNull('submitted_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (reviewed_at - submitted_at))/3600) as avg_hours')
                ->value('avg_hours'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
