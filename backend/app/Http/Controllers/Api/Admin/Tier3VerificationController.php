<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessVerification;
use App\Models\SanctionsScreeningResult;
use App\Services\SanctionsScreeningService;
use App\Services\BusinessVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Tier3VerificationController extends Controller
{
    protected SanctionsScreeningService $sanctionsService;
    protected BusinessVerificationService $businessService;

    public function __construct(
        SanctionsScreeningService $sanctionsService,
        BusinessVerificationService $businessService
    ) {
        $this->sanctionsService = $sanctionsService;
        $this->businessService = $businessService;
    }

    /**
     * List Tier 3 verifications
     */
    public function index(Request $request)
    {
        $query = BusinessVerification::where('tier', 'tier3')
            ->with(['user:id,full_name,email', 'beneficialOwners', 'eddReview', 'sanctionsScreenings'])
            ->orderBy('submitted_at', 'desc');

        if ($request->has('status')) {
            $query->where('verification_status', $request->status);
        }

        $verifications = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $verifications,
        ]);
    }

    /**
     * Run sanctions screening for all UBOs
     */
    public function runSanctionsScreening($id)
    {
        try {
            $verification = BusinessVerification::where('tier', 'tier3')
                ->with('beneficialOwners')
                ->findOrFail($id);

            $results = $this->sanctionsService->screenAllUbos($verification);

            return response()->json([
                'success' => true,
                'message' => 'Sanctions screening completed',
                'data' => [
                    'results' => $results,
                    'all_cleared' => $verification->fresh()->sanctions_screening_completed,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to run sanctions screening',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Clear a sanctions match
     */
    public function clearSanctionsMatch(Request $request, $resultId)
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = SanctionsScreeningResult::findOrFail($resultId);

            $this->sanctionsService->clearMatch($result, $request->user(), $request->notes);

            return response()->json([
                'success' => true,
                'message' => 'Sanctions match cleared',
                'data' => $result->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear match',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Start EDD review
     */
    public function startEdd(Request $request, $id)
    {
        try {
            $verification = BusinessVerification::where('tier', 'tier3')
                ->with('eddReview')
                ->findOrFail($id);

            if ($verification->eddReview->isInProgress() || $verification->eddReview->isCompleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'EDD already started or completed',
                ], 400);
            }

            $verification->eddReview->markAsStarted($request->user());

            return response()->json([
                'success' => true,
                'message' => 'EDD review started',
                'data' => $verification->fresh()->eddReview,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start EDD',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Complete EDD review
     */
    public function completeEdd(Request $request, $id)
    {
        try {
            $verification = BusinessVerification::where('tier', 'tier3')
                ->with('eddReview')
                ->findOrFail($id);

            $verification->eddReview->markAsCompleted($request->user());
            
            $verification->update([
                'edd_completed' => true,
                'edd_completed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'EDD review completed',
                'data' => $verification->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete EDD',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Approve Tier 3 verification
     */
    public function approve(Request $request, $id)
    {
        try {
            $verification = BusinessVerification::where('tier', 'tier3')
                ->with(['beneficialOwners', 'eddReview'])
                ->findOrFail($id);

            // Check if ready for approval
            if (!$verification->isReadyForTier3Verification()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification not ready for approval',
                    'requirements' => [
                        'all_ubos_identified' => $verification->all_ubos_identified,
                        'sanctions_completed' => $verification->sanctions_screening_completed,
                        'edd_completed' => $verification->edd_completed,
                        'ownership_100_percent' => $verification->total_ownership_declared >= 100,
                    ],
                ], 400);
            }

            $approved = $this->businessService->approveVerification($verification, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Tier 3 verification approved successfully. User upgraded to Tier 3.',
                'data' => $approved->load(['user', 'beneficialOwners', 'eddReview']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve verification',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
