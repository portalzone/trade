<?php

namespace App\Services;

use App\Models\ComplianceReport;
use App\Models\DataSubjectRequest;
use App\Models\RecordRetentionPolicy;
use App\Models\ScheduledDeletion;
use App\Models\ComplianceChecklist;
use App\Models\RegulatorySubmission;
use App\Models\User;
use App\Models\Order;
use App\Models\SuspiciousActivityAlert;
use App\Models\KycVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ComplianceService
{
    /**
     * Generate CBN monthly compliance report
     */
    public function generateCBNMonthlyReport(int $year, int $month, ?int $generatedBy = null): ComplianceReport
    {
        $periodStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        // Gather statistics
        $stats = [
            'total_transactions' => Order::whereBetween('created_at', [$periodStart, $periodEnd])->count(),
            'total_volume' => Order::whereBetween('created_at', [$periodStart, $periodEnd])->sum('total_amount'),
            'new_users' => User::whereBetween('created_at', [$periodStart, $periodEnd])->count(),
            'kyc_tier_1' => User::where('kyc_tier', 1)->whereBetween('created_at', [$periodStart, $periodEnd])->count(),
            'kyc_tier_2' => User::where('kyc_tier', 2)->whereBetween('created_at', [$periodStart, $periodEnd])->count(),
            'kyc_tier_3' => User::where('kyc_tier', 3)->whereBetween('created_at', [$periodStart, $periodEnd])->count(),
            'suspicious_alerts' => SuspiciousActivityAlert::whereBetween('created_at', [$periodStart, $periodEnd])->count(),
            'sars_filed' => \App\Models\SuspiciousActivityReport::whereBetween('filed_at', [$periodStart, $periodEnd])->count(),
            'disputes' => \App\Models\Dispute::whereBetween('created_at', [$periodStart, $periodEnd])->count(),
        ];

        // Generate report data
        $reportData = [
            'reporting_entity' => 'T-Trade Platform',
            'report_month' => $periodStart->format('F Y'),
            'transactions' => [
                'count' => $stats['total_transactions'],
                'total_value' => $stats['total_volume'],
                'average_value' => $stats['total_transactions'] > 0 ? $stats['total_volume'] / $stats['total_transactions'] : 0,
            ],
            'users' => [
                'new_registrations' => $stats['new_users'],
                'tier_1_onboarded' => $stats['kyc_tier_1'],
                'tier_2_onboarded' => $stats['kyc_tier_2'],
                'tier_3_onboarded' => $stats['kyc_tier_3'],
            ],
            'aml_compliance' => [
                'alerts_generated' => $stats['suspicious_alerts'],
                'sars_filed' => $stats['sars_filed'],
                'disputes_reported' => $stats['disputes'],
            ],
        ];

        // Create report
        $report = ComplianceReport::create([
            'report_type' => 'CBN_monthly',
            'report_period' => $periodStart->format('Y-m'),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'draft',
            'report_data' => $reportData,
            'statistics' => $stats,
            'generated_by' => $generatedBy,
        ]);

        Log::info('CBN monthly report generated', [
            'report_id' => $report->id,
            'period' => $report->report_period,
        ]);

        return $report;
    }

    /**
     * Generate quarterly risk assessment
     */
    public function generateQuarterlyRiskReport(int $year, int $quarter, ?int $generatedBy = null): ComplianceReport
    {
        $quarterStart = \Carbon\Carbon::create($year, ($quarter - 1) * 3 + 1, 1)->startOfMonth();
        $quarterEnd = $quarterStart->copy()->addMonths(3)->subDay();

        // Risk metrics
        $highRiskUsers = \App\Models\UserRiskProfile::where('risk_level', 'high')
            ->orWhere('risk_level', 'critical')
            ->count();

        $totalAlerts = SuspiciousActivityAlert::whereBetween('created_at', [$quarterStart, $quarterEnd])->count();
        $criticalAlerts = SuspiciousActivityAlert::where('severity', 'critical')
            ->whereBetween('created_at', [$quarterStart, $quarterEnd])
            ->count();

        $reportData = [
            'reporting_entity' => 'T-Trade Platform',
            'report_quarter' => "Q{$quarter} {$year}",
            'risk_assessment' => [
                'high_risk_users' => $highRiskUsers,
                'total_alerts' => $totalAlerts,
                'critical_alerts' => $criticalAlerts,
                'risk_trend' => $this->calculateRiskTrend($quarterStart, $quarterEnd),
            ],
            'mitigation_actions' => $this->getMitigationActions($quarterStart, $quarterEnd),
        ];

        $report = ComplianceReport::create([
            'report_type' => 'quarterly_risk',
            'report_period' => "{$year}-Q{$quarter}",
            'period_start' => $quarterStart,
            'period_end' => $quarterEnd,
            'status' => 'draft',
            'report_data' => $reportData,
            'statistics' => [
                'high_risk_users' => $highRiskUsers,
                'total_alerts' => $totalAlerts,
                'critical_alerts' => $criticalAlerts,
            ],
            'generated_by' => $generatedBy,
        ]);

        return $report;
    }

    /**
     * Calculate risk trend
     */
    protected function calculateRiskTrend($startDate, $endDate): string
    {
        $currentAlerts = SuspiciousActivityAlert::whereBetween('created_at', [$startDate, $endDate])->count();
        
        $previousStart = $startDate->copy()->subMonths(3);
        $previousEnd = $endDate->copy()->subMonths(3);
        $previousAlerts = SuspiciousActivityAlert::whereBetween('created_at', [$previousStart, $previousEnd])->count();

        if ($previousAlerts === 0) {
            return 'stable';
        }

        $percentChange = (($currentAlerts - $previousAlerts) / $previousAlerts) * 100;

        if ($percentChange > 20) {
            return 'increasing';
        } elseif ($percentChange < -20) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Get mitigation actions taken
     */
    protected function getMitigationActions($startDate, $endDate): array
    {
        return [
            'alerts_resolved' => SuspiciousActivityAlert::where('status', 'resolved')
                ->whereBetween('resolved_at', [$startDate, $endDate])
                ->count(),
            'accounts_suspended' => User::where('account_status', 'SUSPENDED')
                ->whereBetween('updated_at', [$startDate, $endDate])
                ->count(),
            'sars_filed' => \App\Models\SuspiciousActivityReport::whereBetween('filed_at', [$startDate, $endDate])->count(),
        ];
    }

    /**
     * Process data subject request (GDPR/NDPR)
     */
    public function processDataSubjectRequest(int $userId, string $requestType, ?string $details = null): DataSubjectRequest
    {
        $request = DataSubjectRequest::create([
            'user_id' => $userId,
            'request_type' => $requestType,
            'status' => 'pending',
            'request_details' => $details,
        ]);

        Log::info('Data subject request created', [
            'request_number' => $request->request_number,
            'user_id' => $userId,
            'type' => $requestType,
        ]);

        return $request;
    }

    /**
     * Export user data (for access/portability requests)
     */
    public function exportUserData(int $userId): array
    {
        $user = User::with([
            'orders',
            'wallet',
            'kycVerifications',
            'storefront',
        ])->findOrFail($userId);

        $data = [
            'personal_information' => [
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'user_type' => $user->user_type,
                'account_created' => $user->created_at->toDateTimeString(),
            ],
            'kyc_information' => [
                'kyc_tier' => $user->kyc_tier,
                'nin_verified' => $user->nin_verified,
                'bvn_verified' => $user->bvn_verified,
                'kyc_status' => $user->kyc_status,
            ],
            'transaction_history' => $user->orders->map(function ($order) {
                return [
                    'order_number' => $order->order_number,
                    'amount' => $order->total_amount,
                    'date' => $order->created_at->toDateTimeString(),
                    'status' => $order->status,
                ];
            }),
            'wallet' => [
                'balance' => $user->wallet?->balance ?? 0,
                'locked_balance' => $user->wallet?->locked_balance ?? 0,
            ],
        ];

        // Save to file
        $filename = "user_data_{$userId}_" . time() . ".json";
        $path = "exports/data-subjects/{$filename}";
        Storage::put($path, json_encode($data, JSON_PRETTY_PRINT));

        return [
            'path' => $path,
            'data' => $data,
        ];
    }

    /**
     * Delete user data (for deletion requests)
     */
    public function deleteUserData(int $userId, ?string $reason = null): void
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($userId);

            // Anonymize instead of hard delete to maintain transaction integrity
            $user->update([
                'full_name' => 'DELETED USER',
                'email' => "deleted_{$userId}_" . time() . "@deleted.local",
                'phone_number' => null,
                'account_status' => 'DELETED',
            ]);

            // Delete sensitive KYC data
            KycVerification::where('user_id', $userId)->delete();

            // Mark in audit log
            AuditService::log(
                'user.data_deletion',
                "User data deleted per GDPR/NDPR request",
                null,
                ['user_id' => $userId],
                ['reason' => $reason],
                []
            );

            DB::commit();

            Log::warning('User data deleted', [
                'user_id' => $userId,
                'reason' => $reason,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Schedule record for deletion based on retention policy
     */
    public function scheduleRecordDeletion(string $recordType, int $recordId, int $policyId): ScheduledDeletion
    {
        $policy = RecordRetentionPolicy::findOrFail($policyId);

        // Determine record date
        $record = $this->getRecord($recordType, $recordId);
        $recordDate = $record->created_at;

        // Calculate deletion date
        $deletionDate = $policy->getDeletionDate($recordDate);

        $scheduled = ScheduledDeletion::create([
            'record_type' => $recordType,
            'record_id' => $recordId,
            'scheduled_for' => $deletionDate,
            'status' => 'pending',
            'policy_id' => $policyId,
        ]);

        Log::info('Record scheduled for deletion', [
            'record_type' => $recordType,
            'record_id' => $recordId,
            'scheduled_for' => $deletionDate->format('Y-m-d'),
        ]);

        return $scheduled;
    }

    /**
     * Get record by type and ID
     */
    protected function getRecord(string $recordType, int $recordId)
    {
        $modelMap = [
            'order' => Order::class,
            'kyc_verification' => KycVerification::class,
            // Add more as needed
        ];

        $model = $modelMap[$recordType] ?? null;
        
        if (!$model) {
            throw new \Exception("Unknown record type: {$recordType}");
        }

        return $model::findOrFail($recordId);
    }

    /**
     * Execute scheduled deletions (run daily via cron)
     */
    public function executeScheduledDeletions(): array
    {
        $deleted = 0;
        $failed = 0;

        $dueForDeletion = ScheduledDeletion::where('status', 'pending')
            ->where('scheduled_for', '<=', today())
            ->with('policy')
            ->get();

        foreach ($dueForDeletion as $scheduled) {
            try {
                $policy = $scheduled->policy;
                
                if ($policy->deletion_method === 'soft_delete') {
                    $record = $this->getRecord($scheduled->record_type, $scheduled->record_id);
                    $record->delete(); // Soft delete
                } elseif ($policy->deletion_method === 'hard_delete') {
                    $record = $this->getRecord($scheduled->record_type, $scheduled->record_id);
                    $record->forceDelete(); // Hard delete
                } elseif ($policy->deletion_method === 'archive') {
                    // Move to archive storage
                    $this->archiveRecord($scheduled->record_type, $scheduled->record_id);
                }

                $scheduled->markAsCompleted("Deleted via {$policy->deletion_method}");
                $deleted++;
            } catch (\Exception $e) {
                $scheduled->markAsFailed($e->getMessage());
                $failed++;

                Log::error('Scheduled deletion failed', [
                    'scheduled_id' => $scheduled->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }

    /**
     * Archive record to long-term storage
     */
    protected function archiveRecord(string $recordType, int $recordId): void
    {
        $record = $this->getRecord($recordType, $recordId);
        
        $archiveData = $record->toArray();
        $filename = "{$recordType}_{$recordId}_" . time() . ".json";
        $path = "archives/{$recordType}/{$filename}";

        Storage::put($path, json_encode($archiveData, JSON_PRETTY_PRINT));

        Log::info('Record archived', [
            'record_type' => $recordType,
            'record_id' => $recordId,
            'path' => $path,
        ]);
    }

    /**
     * Create compliance checklist
     */
    public function createComplianceChecklist(string $type, array $items, int $assignedTo): ComplianceChecklist
    {
        $checklist = ComplianceChecklist::create([
            'checklist_type' => $type,
            'review_date' => today(),
            'checklist_items' => $items,
            'items_total' => count($items),
            'items_completed' => 0,
            'assigned_to' => $assignedTo,
        ]);

        return $checklist;
    }

    /**
     * Submit regulatory filing
     */
    public function submitRegulatoryFiling(
        string $type,
        string $regulator,
        array $data,
        string $submittedBy,
        ?string $filePath = null
    ): RegulatorySubmission {
        
        $submission = RegulatorySubmission::create([
            'submission_type' => $type,
            'regulator' => $regulator,
            'submission_data' => $data,
            'file_path' => $filePath,
            'status' => 'submitted',
            'submitted_by' => $submittedBy,
        ]);

        Log::info('Regulatory submission created', [
            'reference_number' => $submission->reference_number,
            'regulator' => $regulator,
            'type' => $type,
        ]);

        return $submission;
    }

    /**
     * Get compliance dashboard metrics
     */
    public function getComplianceDashboard(): array
    {
        return [
            'pending_data_requests' => DataSubjectRequest::where('status', 'pending')->count(),
            'overdue_checklists' => ComplianceChecklist::where('status', 'overdue')->count(),
            'pending_deletions' => ScheduledDeletion::where('status', 'pending')
                ->where('scheduled_for', '<=', today())
                ->count(),
            'draft_reports' => ComplianceReport::where('status', 'draft')->count(),
            'this_month_reports' => ComplianceReport::whereMonth('created_at', now()->month)->count(),
            'pending_submissions' => RegulatorySubmission::where('status', 'submitted')
                ->whereNull('acknowledged_at')
                ->count(),
        ];
    }
}
