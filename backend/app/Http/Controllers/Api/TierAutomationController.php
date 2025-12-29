<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TierAutomationService;
use App\Models\TierChange;
use App\Models\TierViolation;
use App\Models\TierUpgradeRequest;
use App\Models\NotificationQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TierAutomationController extends Controller
{
    protected TierAutomationService $tierService;

    public function __construct(TierAutomationService $tierService)
    {
        $this->tierService = $tierService;
    }

    /**
     * Get tier change history for user
     */
    public function getTierHistory(Request $request, int $userId)
    {
        try {
            $history = $this->tierService->getTierHistory($userId);

            return response()->json([
                'success' => true,
                'data' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tier history',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Manual tier change (admin only)
     */
    public function manualTierChange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'new_tier' => 'required|integer|min:0|max:3',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tierChange = $this->tierService->manualTierChange(
                $request->input('user_id'),
                $request->input('new_tier'),
                $request->input('reason'),
                $request->user()->id,
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Tier changed successfully',
                'data' => $tierChange,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change tier',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create violation and auto-downgrade if necessary
     */
    public function createViolation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'violation_type' => 'required|string',
            'severity' => 'required|in:minor,moderate,severe,critical',
            'description' => 'required|string',
            'evidence' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->tierService->autoDowngradeOnViolation(
                $request->input('user_id'),
                $request->input('violation_type'),
                $request->input('severity'),
                $request->input('description'),
                $request->input('evidence', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Violation recorded and processed',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process violation',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user violations
     */
    public function getUserViolations(Request $request, int $userId)
    {
        try {
            $violations = $this->tierService->getUserViolations($userId);

            return response()->json([
                'success' => true,
                'data' => $violations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve violations',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Submit tier upgrade request
     */
    public function submitUpgradeRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'requested_tier' => 'required|integer|min:1|max:3',
            'justification' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            // Check if can upgrade
            $canUpgrade = $this->tierService->canUpgrade($user->id, $request->input('requested_tier'));

            if (!$canUpgrade['can_upgrade']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot upgrade tier',
                    'reason' => $canUpgrade['reason'],
                ], 400);
            }

            $upgradeRequest = TierUpgradeRequest::create([
                'user_id' => $user->id,
                'current_tier' => $user->kyc_tier,
                'requested_tier' => $request->input('requested_tier'),
                'justification' => $request->input('justification'),
                'submitted_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Upgrade request submitted',
                'data' => $upgradeRequest,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit request',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all tier upgrade requests (admin)
     */
    public function getUpgradeRequests(Request $request)
    {
        try {
            $requests = TierUpgradeRequest::with(['user', 'reviewer'])
                ->when($request->get('status'), function ($query, $status) {
                    $query->where('status', $status);
                })
                ->orderBy('submitted_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $requests,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve requests',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Review tier upgrade request (admin)
     */
    public function reviewUpgradeRequest(Request $request, int $requestId)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $upgradeRequest = TierUpgradeRequest::findOrFail($requestId);
            $action = $request->input('action');

            if ($action === 'approve') {
                $upgradeRequest->approve($request->user()->id, $request->input('notes'));

                // Actually upgrade the tier
                $this->tierService->manualTierChange(
                    $upgradeRequest->user_id,
                    $upgradeRequest->requested_tier,
                    'upgrade_request_approved',
                    $request->user()->id,
                    "Approved upgrade request #{$upgradeRequest->id}"
                );
            } else {
                $upgradeRequest->reject($request->user()->id, $request->input('notes'));
            }

            return response()->json([
                'success' => true,
                'message' => "Request {$action}d successfully",
                'data' => $upgradeRequest,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to review request',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Process pending notifications (cron job)
     */
    public function processNotifications(Request $request)
    {
        try {
            $limit = $request->get('limit', 100);
            $result = $this->tierService->processPendingNotifications($limit);

            return response()->json([
                'success' => true,
                'message' => 'Notifications processed',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process notifications',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get pending notifications count
     */
    public function getPendingNotifications(Request $request)
    {
        try {
            $count = NotificationQueue::pending()->count();

            return response()->json([
                'success' => true,
                'data' => ['pending_count' => $count],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get count',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all tier changes (admin dashboard)
     */
    public function getAllTierChanges(Request $request)
    {
        try {
            $changes = TierChange::with(['user', 'triggeredBy'])
                ->when($request->get('change_type'), function ($query, $type) {
                    $query->where('change_type', $type);
                })
                ->when($request->get('from_date'), function ($query, $date) {
                    $query->whereDate('created_at', '>=', $date);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $changes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tier changes',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get tier statistics
     */
    public function getTierStatistics(Request $request)
    {
        try {
            $stats = [
                'users_by_tier' => [
                    'tier_0' => \App\Models\User::where('kyc_tier', 0)->count(),
                    'tier_1' => \App\Models\User::where('kyc_tier', 1)->count(),
                    'tier_2' => \App\Models\User::where('kyc_tier', 2)->count(),
                    'tier_3' => \App\Models\User::where('kyc_tier', 3)->count(),
                ],
                'recent_upgrades' => TierChange::where('change_type', 'upgrade')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
                'recent_downgrades' => TierChange::where('change_type', 'downgrade')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
                'pending_upgrade_requests' => TierUpgradeRequest::where('status', 'pending')->count(),
                'active_violations' => TierViolation::whereNull('reviewed_at')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
