<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TransactionMonitoringService;
use App\Models\SuspiciousActivityAlert;
use App\Models\SuspiciousActivityReport;
use App\Models\UserRiskProfile;
use App\Models\TransactionMonitoringRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MonitoringController extends Controller
{
    protected TransactionMonitoringService $monitoringService;

    public function __construct(TransactionMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    /**
     * Get all pending alerts (Admin only)
     */
    public function getPendingAlerts(Request $request)
    {
        try {
            $alerts = $this->monitoringService->getPendingAlerts();

            return response()->json([
                'success' => true,
                'data' => $alerts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve alerts',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get alerts by severity
     */
    public function getAlertsBySeverity(Request $request, string $severity)
    {
        try {
            $alerts = SuspiciousActivityAlert::with(['user', 'rule'])
                ->bySeverity($severity)
                ->unresolved()
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $alerts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve alerts',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user risk profile
     */
    public function getUserRiskProfile(Request $request, int $userId)
    {
        try {
            $riskData = $this->monitoringService->getUserRiskLevel($userId);

            return response()->json([
                'success' => true,
                'data' => $riskData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve risk profile',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Resolve an alert
     */
    public function resolveAlert(Request $request, int $alertId)
    {
        $validator = Validator::make($request->all(), [
            'is_true_positive' => 'required|boolean',
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
            $this->monitoringService->resolveAlert(
                $alertId,
                $request->boolean('is_true_positive'),
                $request->input('notes'),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Alert resolved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve alert',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all SARs
     */
    public function getSARs(Request $request)
    {
        try {
            $sars = SuspiciousActivityReport::with(['user', 'alert'])
                ->when($request->get('status'), function ($query, $status) {
                    $query->where('filing_status', $status);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $sars,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve SARs',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Submit SAR
     */
    public function submitSAR(Request $request, int $sarId)
    {
        try {
            $sar = SuspiciousActivityReport::findOrFail($sarId);
            $sar->markAsSubmitted($request->user()->full_name);

            return response()->json([
                'success' => true,
                'message' => 'SAR submitted successfully',
                'data' => $sar,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit SAR',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all monitoring rules
     */
    public function getRules(Request $request)
    {
        try {
            $rules = TransactionMonitoringRule::when(
                $request->get('active_only'),
                fn($query) => $query->where('is_active', true)
            )
            ->orderBy('priority', 'asc')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $rules,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve rules',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create monitoring rule
     */
    public function createRule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rule_name' => 'required|string|max:255',
            'rule_type' => 'required|in:velocity,threshold,category,geolocation,pattern',
            'severity' => 'required|in:low,medium,high,critical',
            'conditions' => 'required|array',
            'description' => 'required|string',
            'priority' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $rule = TransactionMonitoringRule::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Monitoring rule created successfully',
                'data' => $rule,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create rule',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update monitoring rule
     */
    public function updateRule(Request $request, int $ruleId)
    {
        $validator = Validator::make($request->all(), [
            'rule_name' => 'nullable|string|max:255',
            'severity' => 'nullable|in:low,medium,high,critical',
            'conditions' => 'nullable|array',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $rule = TransactionMonitoringRule::findOrFail($ruleId);
            $rule->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Rule updated successfully',
                'data' => $rule,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update rule',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete monitoring rule
     */
    public function deleteRule(int $ruleId)
    {
        try {
            $rule = TransactionMonitoringRule::findOrFail($ruleId);
            $rule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rule deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete rule',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get monitoring dashboard stats
     */
    public function getDashboardStats(Request $request)
    {
        try {
            $stats = [
                'pending_alerts' => SuspiciousActivityAlert::where('status', 'new')->count(),
                'critical_alerts' => SuspiciousActivityAlert::where('severity', 'critical')
                    ->where('status', 'new')
                    ->count(),
                'active_sars' => SuspiciousActivityReport::where('filing_status', 'draft')->count(),
                'high_risk_users' => UserRiskProfile::where('risk_level', 'high')->count(),
                'critical_risk_users' => UserRiskProfile::where('risk_level', 'critical')->count(),
                'alerts_today' => SuspiciousActivityAlert::whereDate('created_at', today())->count(),
                'alerts_this_week' => SuspiciousActivityAlert::where('created_at', '>=', now()->subWeek())->count(),
                'false_positive_rate' => $this->calculateFalsePositiveRate(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stats',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Calculate false positive rate
     */
    protected function calculateFalsePositiveRate(): float
    {
        $total = SuspiciousActivityAlert::whereIn('status', ['resolved', 'false_positive'])->count();
        
        if ($total === 0) {
            return 0;
        }

        $falsePositives = SuspiciousActivityAlert::where('status', 'false_positive')->count();
        
        return round(($falsePositives / $total) * 100, 2);
    }
}
