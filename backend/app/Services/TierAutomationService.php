<?php

namespace App\Services;

use App\Models\User;
use App\Models\TierChange;
use App\Models\TierViolation;
use App\Models\TierUpgradeRequest;
use App\Models\NotificationQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TierAutomationService
{
    /**
     * Auto-upgrade tier on KYC approval
     */
    public function autoUpgradeOnKycApproval(int $userId, int $newTier, string $kycType): void
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($userId);
            $oldTier = $user->kyc_tier;

            // Only upgrade if new tier is higher
            if ($newTier <= $oldTier) {
                DB::rollBack();
                return;
            }

            // Update user tier
            $user->update([
                'kyc_tier' => $newTier,
                'kyc_status' => 'APPROVED',
            ]);

            // Record tier change
            $tierChange = TierChange::create([
                'user_id' => $userId,
                'from_tier' => $oldTier,
                'to_tier' => $newTier,
                'change_type' => 'upgrade',
                'reason' => "kyc_{$kycType}_approved",
                'notes' => "Auto-upgraded from Tier {$oldTier} to Tier {$newTier} upon {$kycType} KYC approval",
                'metadata' => [
                    'kyc_type' => $kycType,
                    'auto_upgrade' => true,
                ],
            ]);

            // Queue notification
            $this->queueTierChangeNotification($tierChange);

            // Update transaction limits
            $this->updateTransactionLimits($user);

            // Audit log
            AuditService::log(
                'tier.auto_upgrade',
                "User auto-upgraded to Tier {$newTier}",
                null,
                ['user_id' => $userId],
                [
                    'from_tier' => $oldTier,
                    'to_tier' => $newTier,
                    'reason' => $kycType,
                ],
                []
            );

            DB::commit();

            Log::info('Auto tier upgrade completed', [
                'user_id' => $userId,
                'from_tier' => $oldTier,
                'to_tier' => $newTier,
                'kyc_type' => $kycType,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Auto tier upgrade failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Auto-downgrade tier on violation
     */
    public function autoDowngradeOnViolation(
        int $userId,
        string $violationType,
        string $severity,
        string $description,
        array $evidence = []
    ): void {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($userId);
            $currentTier = $user->kyc_tier;

            // Determine new tier based on severity
            $newTier = $this->calculateDowngradeTier($currentTier, $severity);

            // Record violation
            $violation = TierViolation::create([
                'user_id' => $userId,
                'violation_type' => $violationType,
                'severity' => $severity,
                'description' => $description,
                'evidence' => $evidence,
                'action_taken' => $newTier < $currentTier ? 'tier_downgrade' : 'warning',
                'tier_affected' => $newTier < $currentTier,
                'previous_tier' => $currentTier,
                'new_tier' => $newTier,
            ]);

            // Only downgrade if necessary
            if ($newTier < $currentTier) {
                $user->update(['kyc_tier' => $newTier]);

                // Record tier change
                $tierChange = TierChange::create([
                    'user_id' => $userId,
                    'from_tier' => $currentTier,
                    'to_tier' => $newTier,
                    'change_type' => 'downgrade',
                    'reason' => $violationType,
                    'notes' => "Auto-downgraded due to {$severity} violation: {$violationType}",
                    'metadata' => [
                        'violation_id' => $violation->id,
                        'severity' => $severity,
                        'auto_downgrade' => true,
                    ],
                ]);

                // Queue notification
                $this->queueTierChangeNotification($tierChange);

                // Update transaction limits
                $this->updateTransactionLimits($user);
            }

            // Audit log
            AuditService::log(
                'tier.violation_downgrade',
                "Tier downgrade due to violation",
                null,
                ['user_id' => $userId],
                [
                    'violation_type' => $violationType,
                    'severity' => $severity,
                    'from_tier' => $currentTier,
                    'to_tier' => $newTier,
                ],
                []
            );

            DB::commit();

            Log::warning('Tier downgrade on violation', [
                'user_id' => $userId,
                'violation_type' => $violationType,
                'from_tier' => $currentTier,
                'to_tier' => $newTier,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Auto tier downgrade failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Calculate downgrade tier based on severity
     */
    protected function calculateDowngradeTier(int $currentTier, string $severity): int
    {
        switch ($severity) {
            case 'critical':
                return 0; // Downgrade to Tier 0 (unverified)
            case 'severe':
                return max(0, $currentTier - 2);
            case 'moderate':
                return max(0, $currentTier - 1);
            case 'minor':
                return $currentTier; // Warning only, no downgrade
            default:
                return $currentTier;
        }
    }

    /**
     * Manual tier change (admin action)
     */
    public function manualTierChange(
        int $userId,
        int $newTier,
        string $reason,
        ?int $adminId = null,
        ?string $notes = null
    ): TierChange {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($userId);
            $oldTier = $user->kyc_tier;

            // Update user tier
            $user->update(['kyc_tier' => $newTier]);

            // Record tier change
            $tierChange = TierChange::create([
                'user_id' => $userId,
                'from_tier' => $oldTier,
                'to_tier' => $newTier,
                'change_type' => 'manual',
                'reason' => $reason,
                'notes' => $notes,
                'triggered_by' => $adminId,
                'metadata' => ['manual_override' => true],
            ]);

            // Queue notification
            $this->queueTierChangeNotification($tierChange);

            // Update transaction limits
            $this->updateTransactionLimits($user);

            // Audit log
            AuditService::log(
                'tier.manual_change',
                "Manual tier change by admin",
                null,
                ['user_id' => $userId],
                [
                    'from_tier' => $oldTier,
                    'to_tier' => $newTier,
                    'reason' => $reason,
                    'admin_id' => $adminId,
                ],
                []
            );

            DB::commit();

            return $tierChange;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update transaction limits based on new tier
     */
    protected function updateTransactionLimits(User $user): void
    {
        $limits = $this->getTierLimits($user->kyc_tier);

        \App\Models\UserTransactionLimit::updateOrCreate(
            ['user_id' => $user->id],
            [
                'per_transaction' => $limits['per_transaction'],
                'daily_limit' => $limits['daily'],
                'monthly_limit' => $limits['monthly'],
            ]
        );

        Log::info('Transaction limits updated', [
            'user_id' => $user->id,
            'tier' => $user->kyc_tier,
            'limits' => $limits,
        ]);
    }

    /**
     * Get transaction limits for tier
     */
    protected function getTierLimits(int $tier): array
    {
        $limits = [
            0 => [ // Tier 0: Unverified
                'per_transaction' => 0,
                'daily' => 0,
                'monthly' => 0,
            ],
            1 => [ // Tier 1: Basic KYC
                'per_transaction' => 100000,
                'daily' => 200000,
                'monthly' => 500000,
            ],
            2 => [ // Tier 2: Enhanced KYC
                'per_transaction' => 500000,
                'daily' => 2000000,
                'monthly' => 20000000,
            ],
            3 => [ // Tier 3: Enterprise
                'per_transaction' => PHP_INT_MAX,
                'daily' => PHP_INT_MAX,
                'monthly' => PHP_INT_MAX,
            ],
        ];

        return $limits[$tier] ?? $limits[0];
    }

    /**
     * Queue tier change notification
     */
    protected function queueTierChangeNotification(TierChange $tierChange): void
    {
        $user = $tierChange->user;

        // Email notification
        NotificationQueue::create([
            'user_id' => $user->id,
            'notification_type' => 'tier_change',
            'channel' => 'email',
            'priority' => $tierChange->isDowngrade() ? 'high' : 'normal',
            'status' => 'pending',
            'data' => [
                'tier_change_id' => $tierChange->id,
                'from_tier' => $tierChange->from_tier,
                'to_tier' => $tierChange->to_tier,
                'change_type' => $tierChange->change_type,
                'reason' => $tierChange->reason,
                'email' => $user->email,
                'full_name' => $user->full_name,
            ],
        ]);

        // SMS notification for downgrades
        if ($tierChange->isDowngrade() && $user->phone_number) {
            NotificationQueue::create([
                'user_id' => $user->id,
                'notification_type' => 'tier_downgrade_alert',
                'channel' => 'sms',
                'priority' => 'high',
                'status' => 'pending',
                'data' => [
                    'phone_number' => $user->phone_number,
                    'from_tier' => $tierChange->from_tier,
                    'to_tier' => $tierChange->to_tier,
                    'reason' => $tierChange->reason,
                ],
            ]);
        }
    }

    /**
     * Process pending notifications
     */
    public function processPendingNotifications(int $limit = 100): array
    {
        $sent = 0;
        $failed = 0;

        $notifications = NotificationQueue::pending()
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        foreach ($notifications as $notification) {
            try {
                $this->sendNotification($notification);
                $notification->markAsSent();
                $sent++;
            } catch (\Exception $e) {
                $notification->markAsFailed($e->getMessage());
                $failed++;

                Log::error('Notification send failed', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Send notification based on channel
     */
    protected function sendNotification(NotificationQueue $notification): void
    {
        switch ($notification->channel) {
            case 'email':
                $this->sendEmailNotification($notification);
                break;
            case 'sms':
                $this->sendSMSNotification($notification);
                break;
            case 'push':
                $this->sendPushNotification($notification);
                break;
            default:
                throw new \Exception("Unknown notification channel: {$notification->channel}");
        }
    }

    /**
     * Send email notification
     */
    protected function sendEmailNotification(NotificationQueue $notification): void
    {
        // In production, use Mail facade
        // For now, just log
        Log::info('Email notification sent', [
            'notification_id' => $notification->id,
            'type' => $notification->notification_type,
            'to' => $notification->data['email'] ?? 'unknown',
        ]);

        // Example:
        // Mail::to($notification->data['email'])->send(new TierChangeNotification($notification->data));
    }

    /**
     * Send SMS notification
     */
    protected function sendSMSNotification(NotificationQueue $notification): void
    {
        // In production, integrate with Twilio/Termii
        Log::info('SMS notification sent', [
            'notification_id' => $notification->id,
            'type' => $notification->notification_type,
            'to' => $notification->data['phone_number'] ?? 'unknown',
        ]);
    }

    /**
     * Send push notification
     */
    protected function sendPushNotification(NotificationQueue $notification): void
    {
        // In production, integrate with FCM/APNs
        Log::info('Push notification sent', [
            'notification_id' => $notification->id,
            'type' => $notification->notification_type,
        ]);
    }

    /**
     * Get tier change history for user
     */
    public function getTierHistory(int $userId)
    {
        return TierChange::where('user_id', $userId)
            ->with('triggeredBy')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get user violations
     */
    public function getUserViolations(int $userId)
    {
        return TierViolation::where('user_id', $userId)
            ->with('reviewer')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if user can be upgraded
     */
    public function canUpgrade(int $userId, int $targetTier): array
    {
        $user = User::findOrFail($userId);

        if ($user->kyc_tier >= $targetTier) {
            return [
                'can_upgrade' => false,
                'reason' => 'User already at or above target tier',
            ];
        }

        // Check for active violations
        $activeViolations = TierViolation::where('user_id', $userId)
            ->where('severity', 'severe')
            ->orWhere('severity', 'critical')
            ->whereNull('reviewed_at')
            ->count();

        if ($activeViolations > 0) {
            return [
                'can_upgrade' => false,
                'reason' => 'User has active severe violations',
            ];
        }

        // Check KYC requirements for target tier
        $kycComplete = $this->checkKycRequirements($user, $targetTier);

        if (!$kycComplete) {
            return [
                'can_upgrade' => false,
                'reason' => 'KYC requirements not met for target tier',
            ];
        }

        return [
            'can_upgrade' => true,
            'reason' => 'All requirements met',
        ];
    }

    /**
     * Check KYC requirements for tier
     */
    protected function checkKycRequirements(User $user, int $targetTier): bool
    {
        switch ($targetTier) {
            case 1:
                return $user->nin_verified && $user->bvn_verified;
            case 2:
                return $user->kyc_tier >= 1 && $user->kyc_status === 'APPROVED';
            case 3:
                return $user->kyc_tier >= 2 && $user->kyc_status === 'APPROVED';
            default:
                return true;
        }
    }
}
