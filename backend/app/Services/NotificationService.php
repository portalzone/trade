<?php

namespace App\Services;

use App\Models\User;
use App\Models\Dispute;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send KYC approval notification
     */
    public function sendKycApproved(User $user, int $tier)
    {
        try {
            Mail::send('emails.kyc-approved', [
                'user' => $user,
                'tier' => $tier,
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('KYC Verification Approved - T-Trade');
            });

            Log::info("KYC approval email sent to {$user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send KYC approval email: {$e->getMessage()}");
        }
    }

    /**
     * Send KYC rejection notification
     */
    public function sendKycRejected(User $user, int $tier, string $reason)
    {
        try {
            Mail::send('emails.kyc-rejected', [
                'user' => $user,
                'tier' => $tier,
                'reason' => $reason,
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('KYC Verification Update - T-Trade');
            });

            Log::info("KYC rejection email sent to {$user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send KYC rejection email: {$e->getMessage()}");
        }
    }

    /**
     * Send dispute update notification
     */
    public function sendDisputeUpdate(User $user, Dispute $dispute, ?string $resolution = null)
    {
        try {
            Mail::send('emails.dispute-update', [
                'user' => $user,
                'dispute' => $dispute,
                'resolution' => $resolution,
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Dispute Update - T-Trade');
            });

            Log::info("Dispute update email sent to {$user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send dispute update email: {$e->getMessage()}");
        }
    }

    /**
     * Send MFA setup/disable notification
     */
    public function sendMfaNotification(User $user, string $action)
    {
        try {
            Mail::send('emails.mfa-setup', [
                'user' => $user,
                'action' => $action, // 'enabled' or 'disabled'
            ], function ($message) use ($user, $action) {
                $message->to($user->email)
                    ->subject("MFA {$action} - T-Trade");
            });

            Log::info("MFA {$action} email sent to {$user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send MFA email: {$e->getMessage()}");
        }
    }
}
