<?php

namespace App\Services;

use App\Models\User;
use App\Models\BankAccount;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

class WithdrawalService
{
    protected float $minWithdrawal = 1000;
    protected float $feePercentage = 1.0;

    public function createWithdrawal(User $user, float $amount, int $bankAccountId): Withdrawal
    {
        DB::beginTransaction();

        try {
            if ($amount < $this->minWithdrawal) {
                throw new \Exception("Minimum withdrawal amount is ₦" . number_format($this->minWithdrawal, 2));
            }

            $bankAccount = BankAccount::where('id', $bankAccountId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            if (!$bankAccount->is_verified) {
                throw new \Exception('Bank account must be verified before withdrawal');
            }

            $fee = ($amount * $this->feePercentage) / 100;
            $netAmount = $amount - $fee;

            if (!$user->wallet) {
                throw new \Exception('Wallet not found');
            }

            // FIXED: Use available_balance instead of balance
            if ($user->wallet->available_balance < $amount) {
                throw new \Exception('Insufficient wallet balance. Available: ₦' . number_format($user->wallet->available_balance, 2));
            }

            // FIXED: Deduct from available_balance
            $user->wallet->decrement('available_balance', $amount);

            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'bank_account_id' => $bankAccount->id,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'reference' => Withdrawal::generateReference(),
                'status' => 'pending',
            ]);

            AuditService::log(
                'withdrawal.created',
                "Withdrawal request created: ₦{$amount}",
                $withdrawal,
                [],
                ['amount' => $amount, 'fee' => $fee, 'net_amount' => $netAmount],
                ['user_id' => $user->id]
            );

            DB::commit();
            return $withdrawal->load('bankAccount');
        } catch (\Exception $e) {
            DB::rollBack();
            AuditService::logFailure('withdrawal.create_failed', 'Failed to create withdrawal', $e->getMessage(), ['user_id' => $user->id, 'amount' => $amount]);
            throw $e;
        }
    }

    public function approveWithdrawal(Withdrawal $withdrawal, User $admin): Withdrawal
    {
        DB::beginTransaction();
        try {
            if ($withdrawal->status !== 'pending') {
                throw new \Exception('Only pending withdrawals can be approved');
            }

            $withdrawal->update([
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            AuditService::log('withdrawal.approved', "Withdrawal #{$withdrawal->id} approved by admin", $withdrawal, ['status' => 'pending'], ['status' => 'approved', 'approved_by' => $admin->id], ['admin_id' => $admin->id]);
            DB::commit();
            return $withdrawal->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            AuditService::logFailure('withdrawal.approve_failed', "Failed to approve withdrawal #{$withdrawal->id}", $e->getMessage(), ['withdrawal_id' => $withdrawal->id, 'admin_id' => $admin->id]);
            throw $e;
        }
    }

    public function rejectWithdrawal(Withdrawal $withdrawal, User $admin, string $reason): Withdrawal
    {
        DB::beginTransaction();
        try {
            if ($withdrawal->status !== 'pending') {
                throw new \Exception('Only pending withdrawals can be rejected');
            }

            // FIXED: Refund to available_balance
            $withdrawal->user->wallet->increment('available_balance', $withdrawal->amount);

            $withdrawal->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            AuditService::log('withdrawal.rejected', "Withdrawal #{$withdrawal->id} rejected: {$reason}", $withdrawal, ['status' => 'pending'], ['status' => 'rejected'], ['admin_id' => $admin->id, 'reason' => $reason]);
            DB::commit();
            return $withdrawal->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            AuditService::logFailure('withdrawal.reject_failed', "Failed to reject withdrawal #{$withdrawal->id}", $e->getMessage(), ['withdrawal_id' => $withdrawal->id, 'admin_id' => $admin->id]);
            throw $e;
        }
    }

    public function completeWithdrawal(Withdrawal $withdrawal): Withdrawal
    {
        DB::beginTransaction();
        try {
            if (!in_array($withdrawal->status, ['approved', 'processing'])) {
                throw new \Exception('Only approved/processing withdrawals can be completed');
            }

            $withdrawal->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            AuditService::log('withdrawal.completed', "Withdrawal #{$withdrawal->id} completed successfully", $withdrawal, ['status' => $withdrawal->getOriginal('status')], ['status' => 'completed'], []);
            DB::commit();
            return $withdrawal->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            AuditService::logFailure('withdrawal.complete_failed', "Failed to complete withdrawal #{$withdrawal->id}", $e->getMessage(), ['withdrawal_id' => $withdrawal->id]);
            throw $e;
        }
    }

    public function cancelWithdrawal(Withdrawal $withdrawal, User $user): Withdrawal
    {
        DB::beginTransaction();
        try {
            if ($withdrawal->user_id !== $user->id) {
                throw new \Exception('Unauthorized');
            }

            if (!$withdrawal->canBeCancelled()) {
                throw new \Exception('This withdrawal cannot be cancelled');
            }

            // FIXED: Refund to available_balance
            $withdrawal->user->wallet->increment('available_balance', $withdrawal->amount);

            $withdrawal->update([
                'status' => 'rejected',
                'rejection_reason' => 'Cancelled by user',
            ]);

            AuditService::log('withdrawal.cancelled', "Withdrawal #{$withdrawal->id} cancelled by user", $withdrawal, ['status' => $withdrawal->getOriginal('status')], ['status' => 'rejected'], ['user_id' => $user->id]);
            DB::commit();
            return $withdrawal->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            AuditService::logFailure('withdrawal.cancel_failed', "Failed to cancel withdrawal #{$withdrawal->id}", $e->getMessage(), ['withdrawal_id' => $withdrawal->id, 'user_id' => $user->id]);
            throw $e;
        }
    }
}
