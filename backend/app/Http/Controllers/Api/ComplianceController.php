<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ComplianceService;
use App\Models\ComplianceReport;
use App\Models\DataSubjectRequest;
use App\Models\RecordRetentionPolicy;
use App\Models\ComplianceChecklist;
use App\Models\RegulatorySubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ComplianceController extends Controller
{
    protected ComplianceService $complianceService;

    public function __construct(ComplianceService $complianceService)
    {
        $this->complianceService = $complianceService;
    }

    /**
     * Generate CBN monthly report
     */
    public function generateCBNReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $report = $this->complianceService->generateCBNMonthlyReport(
                $request->input('year'),
                $request->input('month'),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'CBN monthly report generated',
                'data' => $report,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Generate quarterly risk report
     */
    public function generateQuarterlyReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:2100',
            'quarter' => 'required|integer|min:1|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $report = $this->complianceService->generateQuarterlyRiskReport(
                $request->input('year'),
                $request->input('quarter'),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Quarterly risk report generated',
                'data' => $report,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all compliance reports
     */
    public function getReports(Request $request)
    {
        try {
            $reports = ComplianceReport::when($request->get('type'), function ($query, $type) {
                    $query->where('report_type', $type);
                })
                ->when($request->get('status'), function ($query, $status) {
                    $query->where('status', $status);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $reports,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reports',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Submit compliance report
     */
    public function submitReport(Request $request, int $reportId)
    {
        $validator = Validator::make($request->all(), [
            'submitted_to' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $report = ComplianceReport::findOrFail($reportId);
            $report->markAsSubmitted(
                $request->input('submitted_to'),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Report submitted successfully',
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit report',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create data subject request
     */
    public function createDataSubjectRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_type' => 'required|in:access,deletion,portability,rectification',
            'request_details' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $dsRequest = $this->complianceService->processDataSubjectRequest(
                $request->user()->id,
                $request->input('request_type'),
                $request->input('request_details')
            );

            return response()->json([
                'success' => true,
                'message' => 'Data subject request created',
                'data' => $dsRequest,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create request',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all data subject requests
     */
    public function getDataSubjectRequests(Request $request)
    {
        try {
            $requests = DataSubjectRequest::with(['user', 'assignedTo'])
                ->when($request->get('status'), function ($query, $status) {
                    $query->where('status', $status);
                })
                ->when($request->get('type'), function ($query, $type) {
                    $query->where('request_type', $type);
                })
                ->orderBy('created_at', 'desc')
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
     * Process data subject request (admin)
     */
    public function processDataRequest(Request $request, int $requestId)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $dsRequest = DataSubjectRequest::findOrFail($requestId);
            $action = $request->input('action');

            if ($action === 'approve') {
                $dsRequest->markAsProcessing($request->user()->id);

                if ($dsRequest->request_type === 'access' || $dsRequest->request_type === 'portability') {
                    // Export user data
                    $export = $this->complianceService->exportUserData($dsRequest->user_id);
                    $dsRequest->markAsCompleted($export['path']);
                } elseif ($dsRequest->request_type === 'deletion') {
                    // Delete user data
                    $this->complianceService->deleteUserData($dsRequest->user_id, $request->input('notes'));
                    $dsRequest->markAsCompleted();
                }
            } else {
                $dsRequest->markAsRejected($request->input('notes'));
            }

            return response()->json([
                'success' => true,
                'message' => "Request {$action}d successfully",
                'data' => $dsRequest,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process request',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get retention policies
     */
    public function getRetentionPolicies(Request $request)
    {
        try {
            $policies = RecordRetentionPolicy::when($request->get('active_only'), function ($query) {
                    $query->where('is_active', true);
                })
                ->orderBy('data_type')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $policies,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve policies',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create retention policy
     */
    public function createRetentionPolicy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data_type' => 'required|string|max:255',
            'retention_years' => 'required|integer|min:1|max:50',
            'description' => 'required|string',
            'deletion_method' => 'required|in:soft_delete,hard_delete,archive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $policy = RecordRetentionPolicy::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Retention policy created',
                'data' => $policy,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create policy',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Execute scheduled deletions (cron job)
     */
    public function executeScheduledDeletions(Request $request)
    {
        try {
            $result = $this->complianceService->executeScheduledDeletions();

            return response()->json([
                'success' => true,
                'message' => 'Scheduled deletions executed',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute deletions',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get compliance dashboard
     */
    public function getDashboard(Request $request)
    {
        try {
            $dashboard = $this->complianceService->getComplianceDashboard();

            return response()->json([
                'success' => true,
                'data' => $dashboard,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get compliance checklists
     */
    public function getChecklists(Request $request)
    {
        try {
            $checklists = ComplianceChecklist::with('assignee')
                ->when($request->get('status'), function ($query, $status) {
                    $query->where('status', $status);
                })
                ->orderBy('review_date', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $checklists,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve checklists',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update checklist item
     */
    public function updateChecklistItem(Request $request, int $checklistId, int $itemIndex)
    {
        try {
            $checklist = ComplianceChecklist::findOrFail($checklistId);
            $checklist->completeItem($itemIndex);

            return response()->json([
                'success' => true,
                'message' => 'Checklist item updated',
                'data' => $checklist,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update item',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get regulatory submissions
     */
    public function getSubmissions(Request $request)
    {
        try {
            $submissions = RegulatorySubmission::when($request->get('regulator'), function ($query, $regulator) {
                    $query->where('regulator', $regulator);
                })
                ->orderBy('submitted_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $submissions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve submissions',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
