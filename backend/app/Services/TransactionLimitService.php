<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\UserTransactionLimit;
use App\Models\TransactionLimit;
use Carbon\Carbon;

class TransactionLimitService
{
    /**
     * Check if user can make a transaction of given amount
     */
    public function canTransact(User $user, float $amount): array
    {
        // Get or create user's transaction limits
        $limits = $this->getUserLimits($user);

        // Check per-transaction limit
        if ($amount > $limits->per_transaction_limit) {
            return [
                'allowed' => false,
                'reason' => 'per_transaction',
                'limit' => $limits->per_transaction_limit,
                'amount' => $amount,
                'message' => "Transaction amount (₦" . number_format($amount, 2) . ") exceeds your per-transaction limit of ₦" . number_format($limits->per_transaction_limit, 2),
            ];
        }

        // Check daily limit
        $dailySpent = $this->getDailySpent($user);
        if (($dailySpent + $amount) > $limits->daily_limit) {
            return [
                'allowed' => false,
                'reason' => 'daily',
                'limit' => $limits->daily_limit,
                'spent' => $dailySpent,
                'amount' => $amount,
                'remaining' => $limits->daily_limit - $dailySpent,
                'message' => "This transaction would exceed your daily limit of ₦" . number_format($limits->daily_limit, 2) . ". You have ₦" . number_format($limits->daily_limit - $dailySpent, 2) . " remaining today.",
            ];
        }

        // Check monthly limit
        $monthlySpent = $this->getMonthlySpent($user);
        if (($monthlySpent + $amount) > $limits->monthly_limit) {
            return [
                'allowed' => false,
                'reason' => 'monthly',
                'limit' => $limits->monthly_limit,
                'spent' => $monthlySpent,
                'amount' => $amount,
                'remaining' => $limits->monthly_limit - $monthlySpent,
                'message' => "This transaction would exceed your monthly limit of ₦" . number_format($limits->monthly_limit, 2) . ". You have ₦" . number_format($limits->monthly_limit - $monthlySpent, 2) . " remaining this month.",
            ];
        }

        return [
            'allowed' => true,
            'message' => 'Transaction allowed',
        ];
    }

    /**
     * Get user's transaction limits (auto-create from tier defaults)
     */
    public function getUserLimits(User $user): UserTransactionLimit
    {
        $userLimit = UserTransactionLimit::where('user_id', $user->id)->first();

        // If user has limits, check if tier matches current user tier
        if ($userLimit) {
            $expectedTier = 'tier' . $user->kyc_tier;
            
            // If tier doesn't match, upgrade limits
            if ($userLimit->tier !== $expectedTier) {
                $this->upgradeLimits($user);
                $userLimit = $userLimit->fresh();
            }
            
            return $userLimit;
        }

        // Create new limits from tier defaults
        return $this->createLimitsFromTier($user);
    }

    /**
     * Create user limits from tier defaults
     */
    protected function createLimitsFromTier(User $user): UserTransactionLimit
    {
        $tierDefaults = TransactionLimit::getForTier($user->kyc_tier);

        if (!$tierDefaults) {
            // Fallback to tier 1 if tier defaults not found
            $tierDefaults = TransactionLimit::getForTier(1);
        }

        return UserTransactionLimit::create([
            'user_id' => $user->id,
            'tier' => 'tier' . $user->kyc_tier,
            'per_transaction_limit' => $tierDefaults->per_transaction_limit,
            'daily_limit' => $tierDefaults->daily_limit,
            'monthly_limit' => $tierDefaults->monthly_limit,
        ]);
    }

    /**
     * Upgrade user limits when tier changes
     */
    public function upgradeLimits(User $user): UserTransactionLimit
    {
        $tierDefaults = TransactionLimit::getForTier($user->kyc_tier);

        if (!$tierDefaults) {
            throw new \Exception("Tier {$user->kyc_tier} limits not found");
        }

        $userLimit = UserTransactionLimit::where('user_id', $user->id)->first();

        if (!$userLimit) {
            return $this->createLimitsFromTier($user);
        }

        $userLimit->update([
            'tier' => 'tier' . $user->kyc_tier,
            'per_transaction_limit' => $tierDefaults->per_transaction_limit,
            'daily_limit' => $tierDefaults->daily_limit,
            'monthly_limit' => $tierDefaults->monthly_limit,
        ]);

        return $userLimit->fresh();
    }

    /**
     * Get user's spending for today
     */
    protected function getDailySpent(User $user): float
    {
        return Order::where('buyer_id', $user->id)
            ->whereIn('order_status', ['IN_ESCROW', 'COMPLETED'])
            ->whereDate('created_at', Carbon::today())
            ->sum('price');
    }

    /**
     * Get user's spending for this month
     */
    protected function getMonthlySpent(User $user): float
    {
        return Order::where('buyer_id', $user->id)
            ->whereIn('order_status', ['IN_ESCROW', 'COMPLETED'])
            ->whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('price');
    }

    /**
     * Get user's limit usage stats
     */
    public function getLimitStats(User $user): array
    {
        $limits = $this->getUserLimits($user);
        $dailySpent = $this->getDailySpent($user);
        $monthlySpent = $this->getMonthlySpent($user);

        return [
            'tier' => $limits->tier,
            'kyc_tier' => $user->kyc_tier,
            'limits' => [
                'per_transaction' => $limits->per_transaction_limit,
                'daily' => $limits->daily_limit,
                'monthly' => $limits->monthly_limit,
            ],
            'usage' => [
                'daily' => [
                    'spent' => $dailySpent,
                    'remaining' => max(0, $limits->daily_limit - $dailySpent),
                    'percentage' => $limits->daily_limit > 0 ? min(100, ($dailySpent / $limits->daily_limit) * 100) : 0,
                ],
                'monthly' => [
                    'spent' => $monthlySpent,
                    'remaining' => max(0, $limits->monthly_limit - $monthlySpent),
                    'percentage' => $limits->monthly_limit > 0 ? min(100, ($monthlySpent / $limits->monthly_limit) * 100) : 0,
                ],
            ],
        ];
    }
}
