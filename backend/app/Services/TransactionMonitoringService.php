<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\TransactionMonitoringRule;
use App\Models\SuspiciousActivityAlert;
use App\Models\SuspiciousActivityReport;
use App\Models\UserRiskProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionMonitoringService
{
    /**
     * Monitor a transaction for suspicious activity
     */
    public function monitorTransaction(array $transactionData): array
    {
        $userId = $transactionData['user_id'];
        $amount = $transactionData['amount'];
        $type = $transactionData['type'] ?? 'payment';

        $alerts = [];
        $totalRiskScore = 0;

        // Get active monitoring rules
        $rules = TransactionMonitoringRule::active()->get();

        foreach ($rules as $rule) {
            $result = $this->evaluateRule($rule, $transactionData);
            
            if ($result['triggered']) {
                $alert = $this->createAlert($userId, $rule, $result, $transactionData);
                $alerts[] = $alert;
                $totalRiskScore += $result['risk_score'];
            }
        }

        // Update user risk profile
        if (!empty($alerts)) {
            $this->updateUserRiskProfile($userId, $alerts);
        }

        // Check if SAR should be filed
        if ($totalRiskScore >= 70 || $this->shouldFileSAR($userId, $alerts)) {
            $this->createSAR($userId, $alerts, $transactionData);
        }

        return [
            'alerts_generated' => count($alerts),
            'total_risk_score' => $totalRiskScore,
            'action_required' => $totalRiskScore >= 50,
        ];
    }

    /**
     * Evaluate a monitoring rule
     */
    protected function evaluateRule(TransactionMonitoringRule $rule, array $data): array
    {
        $triggered = false;
        $riskScore = 0;
        $details = [];

        switch ($rule->rule_type) {
            case 'velocity':
                $result = $this->checkVelocity($data, $rule->conditions);
                $triggered = $result['triggered'];
                $riskScore = $result['score'];
                $details = $result['details'];
                break;

            case 'threshold':
                $result = $this->checkThreshold($data, $rule->conditions);
                $triggered = $result['triggered'];
                $riskScore = $result['score'];
                $details = $result['details'];
                break;

            case 'pattern':
                $result = $this->checkPattern($data, $rule->conditions);
                $triggered = $result['triggered'];
                $riskScore = $result['score'];
                $details = $result['details'];
                break;

            case 'geolocation':
                $result = $this->checkGeolocation($data, $rule->conditions);
                $triggered = $result['triggered'];
                $riskScore = $result['score'];
                $details = $result['details'];
                break;

            case 'category':
                $result = $this->checkCategory($data, $rule->conditions);
                $triggered = $result['triggered'];
                $riskScore = $result['score'];
                $details = $result['details'];
                break;
        }

        return [
            'triggered' => $triggered,
            'risk_score' => $riskScore,
            'details' => $details,
        ];
    }

    /**
     * Check velocity rules (transaction frequency)
     */
    protected function checkVelocity(array $data, array $conditions): array
    {
        $userId = $data['user_id'];
        $timeWindow = $conditions['time_window'] ?? 60; // minutes
        $maxTransactions = $conditions['max_transactions'] ?? 10;
        $minAmount = $conditions['min_amount'] ?? 0;

        // Count recent transactions
        $recentCount = Order::where('buyer_id', $userId)
            ->where('created_at', '>=', now()->subMinutes($timeWindow))
            ->where('total_amount', '>=', $minAmount)
            ->count();

        $triggered = $recentCount >= $maxTransactions;
        $score = 0;

        if ($triggered) {
            $exceedBy = $recentCount - $maxTransactions;
            $score = min(100, 30 + ($exceedBy * 10));
        }

        return [
            'triggered' => $triggered,
            'score' => $score,
            'details' => [
                'transaction_count' => $recentCount,
                'threshold' => $maxTransactions,
                'time_window' => $timeWindow,
            ],
        ];
    }

    /**
     * Check threshold rules (amount limits)
     */
    protected function checkThreshold(array $data, array $conditions): array
    {
        $amount = $data['amount'];
        $dailyLimit = $conditions['daily_limit'] ?? null;
        $singleLimit = $conditions['single_limit'] ?? null;

        $triggered = false;
        $score = 0;
        $details = [];

        // Check single transaction limit
        if ($singleLimit && $amount > $singleLimit) {
            $triggered = true;
            $exceedPercent = (($amount - $singleLimit) / $singleLimit) * 100;
            $score = min(100, 40 + $exceedPercent);
            $details['single_limit_exceeded'] = true;
            $details['amount'] = $amount;
            $details['limit'] = $singleLimit;
        }

        // Check daily cumulative limit
        if ($dailyLimit) {
            $todayTotal = Order::where('buyer_id', $data['user_id'])
                ->whereDate('created_at', today())
                ->sum('total_amount');

            if (($todayTotal + $amount) > $dailyLimit) {
                $triggered = true;
                $score = max($score, 50);
                $details['daily_limit_exceeded'] = true;
                $details['today_total'] = $todayTotal;
                $details['daily_limit'] = $dailyLimit;
            }
        }

        return [
            'triggered' => $triggered,
            'score' => $score,
            'details' => $details,
        ];
    }

    /**
     * Check suspicious patterns
     */
    protected function checkPattern(array $data, array $conditions): array
    {
        $userId = $data['user_id'];
        $triggered = false;
        $score = 0;
        $details = [];

        // Pattern: Rapid account creation + large transaction
        $user = User::find($userId);
        $accountAge = now()->diffInDays($user->created_at);
        
        if ($accountAge < 7 && $data['amount'] > 100000) {
            $triggered = true;
            $score = 60;
            $details['pattern'] = 'new_account_large_transaction';
            $details['account_age_days'] = $accountAge;
        }

        // Pattern: Round number transactions (possible structuring)
        if ($data['amount'] % 100000 === 0 && $data['amount'] >= 100000) {
            $triggered = true;
            $score = max($score, 40);
            $details['pattern'] = 'round_number_structuring';
        }

        // Pattern: Multiple failed transactions before success
        $recentFailed = Order::where('buyer_id', $userId)
            ->where('payment_status', 'FAILED')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($recentFailed >= 3) {
            $triggered = true;
            $score = max($score, 50);
            $details['pattern'] = 'multiple_failed_attempts';
            $details['failed_count'] = $recentFailed;
        }

        return [
            'triggered' => $triggered,
            'score' => $score,
            'details' => $details,
        ];
    }

    /**
     * Check geolocation anomalies
     */
    protected function checkGeolocation(array $data, array $conditions): array
    {
        // This would integrate with IP geolocation services
        // For now, placeholder logic
        
        $triggered = false;
        $score = 0;
        $details = [];

        $userIp = $data['ip_address'] ?? null;
        $userId = $data['user_id'];

        if ($userIp) {
            // Check if IP country matches user country
            // Check for VPN/proxy usage
            // Check for rapid location changes
            
            // Placeholder: Flag if IP is from high-risk country
            $highRiskCountries = ['XX', 'YY']; // Would be configurable
            
            // In production, use IP geolocation API
            // $location = $this->getIpLocation($userIp);
        }

        return [
            'triggered' => $triggered,
            'score' => $score,
            'details' => $details,
        ];
    }

    /**
     * Check category-based rules
     */
    protected function checkCategory(array $data, array $conditions): array
    {
        $triggered = false;
        $score = 0;
        $details = [];

        // High-risk categories (electronics, jewelry, etc.)
        $highRiskCategories = $conditions['high_risk_categories'] ?? [];
        $productCategory = $data['category'] ?? null;

        if ($productCategory && in_array($productCategory, $highRiskCategories)) {
            if ($data['amount'] > 500000) {
                $triggered = true;
                $score = 35;
                $details['high_risk_category'] = $productCategory;
            }
        }

        return [
            'triggered' => $triggered,
            'score' => $score,
            'details' => $details,
        ];
    }

    /**
     * Create suspicious activity alert
     */
    protected function createAlert(
        int $userId,
        TransactionMonitoringRule $rule,
        array $result,
        array $transactionData
    ): SuspiciousActivityAlert {
        
        $severity = $this->calculateSeverity($result['risk_score']);

        $alert = SuspiciousActivityAlert::create([
            'user_id' => $userId,
            'transaction_id' => $transactionData['transaction_id'] ?? null,
            'order_id' => $transactionData['order_id'] ?? null,
            'rule_id' => $rule->id,
            'alert_type' => $rule->rule_type,
            'severity' => $severity,
            'status' => 'new',
            'alert_data' => array_merge($result['details'], [
                'transaction_amount' => $transactionData['amount'],
                'transaction_type' => $transactionData['type'] ?? 'unknown',
            ]),
            'risk_score' => $result['risk_score'],
        ]);

        // Send notification for high/critical severity
        if (in_array($severity, ['red', 'critical'])) {
            $this->sendAlertNotification($alert);
        }

        // Log alert
        Log::warning('Suspicious activity detected', [
            'alert_id' => $alert->id,
            'user_id' => $userId,
            'rule' => $rule->rule_name,
            'severity' => $severity,
            'risk_score' => $result['risk_score'],
        ]);

        return $alert;
    }

    /**
     * Calculate severity from risk score
     */
    protected function calculateSeverity(float $riskScore): string
    {
        if ($riskScore >= 70) {
            return 'critical';
        } elseif ($riskScore >= 50) {
            return 'red';
        } elseif ($riskScore >= 30) {
            return 'yellow';
        }
        return 'yellow';
    }

    /**
     * Update user risk profile
     */
    protected function updateUserRiskProfile(int $userId, array $alerts): void
    {
        $profile = UserRiskProfile::firstOrCreate(
            ['user_id' => $userId],
            [
                'overall_risk_score' => 0,
                'risk_level' => 'low',
                'velocity_score' => 0,
                'pattern_score' => 0,
                'compliance_score' => 100,
            ]
        );

        // Calculate new scores based on alerts
        $velocityScore = 0;
        $patternScore = 0;

        foreach ($alerts as $alert) {
            if ($alert->alert_type === 'velocity') {
                $velocityScore = max($velocityScore, $alert->risk_score);
            } elseif ($alert->alert_type === 'pattern') {
                $patternScore = max($patternScore, $alert->risk_score);
            }

            $profile->incrementAlerts();
        }

        $profile->update([
            'velocity_score' => $velocityScore,
            'pattern_score' => $patternScore,
        ]);

        $profile->updateRiskScore();
    }

    /**
     * Check if SAR should be filed
     */
    protected function shouldFileSAR(int $userId, array $alerts): bool
    {
        // File SAR if multiple critical alerts
        $criticalCount = collect($alerts)
            ->filter(fn($alert) => $alert->severity === 'critical')
            ->count();

        if ($criticalCount >= 2) {
            return true;
        }

        // File SAR if user has pattern of suspicious activity
        $recentAlerts = SuspiciousActivityAlert::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('severity', 'red')
            ->count();

        return $recentAlerts >= 5;
    }

    /**
     * Create Suspicious Activity Report
     */
    protected function createSAR(int $userId, array $alerts, array $transactionData): SuspiciousActivityReport
    {
        $alertIds = collect($alerts)->pluck('id')->toArray();
        $transactionIds = collect($alerts)
            ->pluck('transaction_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $totalAmount = collect($alerts)
            ->sum(fn($alert) => $alert->alert_data['transaction_amount'] ?? 0);

        $summary = $this->generateSARSummary($userId, $alerts);

        $sar = SuspiciousActivityReport::create([
            'user_id' => $userId,
            'summary' => $summary,
            'transactions' => $transactionIds,
            'alerts' => $alertIds,
            'total_amount' => $totalAmount,
            'filing_status' => 'draft',
        ]);

        Log::critical('SAR created', [
            'sar_number' => $sar->sar_number,
            'user_id' => $userId,
            'alert_count' => count($alerts),
            'total_amount' => $totalAmount,
        ]);

        // Notify compliance team
        $this->notifyComplianceTeam($sar);

        return $sar;
    }

    /**
     * Generate SAR summary
     */
    protected function generateSARSummary(int $userId, array $alerts): string
    {
        $user = User::find($userId);
        $alertCount = count($alerts);
        
        $summary = "Suspicious activity detected for user #{$userId} ({$user->full_name}).\n\n";
        $summary .= "Total alerts: {$alertCount}\n\n";
        $summary .= "Alert breakdown:\n";

        foreach ($alerts as $alert) {
            $summary .= "- {$alert->alert_type} (Risk Score: {$alert->risk_score})\n";
        }

        return $summary;
    }

    /**
     * Send alert notification
     */
    protected function sendAlertNotification(SuspiciousActivityAlert $alert): void
    {
        // In production, send email/Slack notification
        // For now, just log
        
        Log::alert('High-severity alert notification', [
            'alert_id' => $alert->id,
            'severity' => $alert->severity,
            'user_id' => $alert->user_id,
        ]);

        // Example: Send email to compliance team
        // Mail::to('compliance@t-trade.com')->send(new SuspiciousActivityAlertMail($alert));
    }

    /**
     * Notify compliance team of SAR
     */
    protected function notifyComplianceTeam(SuspiciousActivityReport $sar): void
    {
        Log::critical('SAR notification to compliance team', [
            'sar_number' => $sar->sar_number,
        ]);

        // In production, send urgent notification
        // Mail::to('compliance@t-trade.com')->send(new SARCreatedMail($sar));
    }

    /**
     * Get user's current risk level
     */
    public function getUserRiskLevel(int $userId): array
    {
        $profile = UserRiskProfile::where('user_id', $userId)->first();

        if (!$profile) {
            return [
                'risk_level' => 'low',
                'risk_score' => 0,
                'total_alerts' => 0,
            ];
        }

        return [
            'risk_level' => $profile->risk_level,
            'risk_score' => $profile->overall_risk_score,
            'total_alerts' => $profile->total_alerts,
            'resolved_alerts' => $profile->resolved_alerts,
            'last_alert_at' => $profile->last_alert_at,
        ];
    }

    /**
     * Get pending alerts for review
     */
    public function getPendingAlerts()
    {
        return SuspiciousActivityAlert::with(['user', 'rule'])
            ->unresolved()
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Resolve alert with feedback
     */
    public function resolveAlert(
        int $alertId,
        bool $isTruePositive,
        ?string $notes = null,
        ?int $reviewedBy = null
    ): void {
        DB::beginTransaction();

        try {
            $alert = SuspiciousActivityAlert::findOrFail($alertId);

            if ($isTruePositive) {
                $alert->markAsResolved($notes);
            } else {
                $alert->markAsFalsePositive($notes);
            }

            // Record feedback
            \App\Models\AlertFeedback::create([
                'alert_id' => $alertId,
                'reviewed_by' => $reviewedBy,
                'is_true_positive' => $isTruePositive,
                'feedback_notes' => $notes,
            ]);

            // Update user risk profile
            if ($isTruePositive) {
                $profile = UserRiskProfile::where('user_id', $alert->user_id)->first();
                if ($profile) {
                    $profile->markAlertResolved();
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
