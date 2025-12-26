<?php

namespace App\Services;

use App\Models\Order;
use App\Models\EscrowLock;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EscrowService
{
    /**
     * Lock funds in escrow for an order
     */
    public function lockFunds(Order $order, Wallet $wallet): EscrowLock
    {
        DB::beginTransaction();

        try {
            $amount = $order->price;

            // Validate wallet has sufficient balance
            if ($wallet->available_balance < $amount) {
                throw new \Exception('Insufficient wallet balance');
            }

            // Deduct from available balance
            $oldAvailableBalance = $wallet->available_balance;
            $wallet->decrement('available_balance', $amount);

            // Add to locked escrow funds
            $wallet->increment('locked_escrow_funds', $amount);

            // Get new balances
            $wallet->refresh();
            $newAvailableBalance = $wallet->available_balance;
            $newLockedBalance = $wallet->locked_escrow_funds;

            // Create escrow lock record
            $escrowLock = EscrowLock::create([
                'order_id' => $order->id,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'platform_fee' => $order->getPlatformFee(),
                'lock_type' => 'ORDER_PAYMENT',
                'locked_at' => now(),
            ]);

            // Create ledger entry
            DB::table('ledger_entries')->insert([
                'transaction_id' => Str::uuid(),
                'wallet_id' => $wallet->id,
                'type' => 'DEBIT',
                'amount' => $amount,
                'description' => "Funds locked in escrow for Order #{$order->id}",
                'reference_table' => 'orders',
                'reference_id' => $order->id,
                'created_at' => now(),
            ]);

            // Audit log
            AuditService::log(
                'escrow.funds_locked',
                "Locked {$amount} in escrow for order #{$order->id}",
                $order,
                ['available_balance' => $oldAvailableBalance],
                [
                    'available_balance' => $newAvailableBalance,
                    'locked_escrow_funds' => $newLockedBalance,
                ],
                [
                    'order_id' => $order->id,
                    'amount' => $amount,
                    'escrow_lock_id' => $escrowLock->id,
                ]
            );

            DB::commit();

            return $escrowLock;
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'escrow.lock_failed',
                "Failed to lock funds for order #{$order->id}",
                $e->getMessage(),
                ['order_id' => $order->id, 'amount' => $order->price]
            );

            throw $e;
        }
    }

    /**
     * Release funds from escrow to seller
     */
    public function releaseFunds(Order $order, EscrowLock $escrowLock): void
    {
        DB::beginTransaction();

        try {
            if (!$escrowLock->isLocked()) {
                throw new \Exception('Escrow is not locked');
            }

            $amount = $escrowLock->amount;
            $platformFee = $escrowLock->platform_fee;
            $sellerPayout = $amount - $platformFee;

            // Get buyer's wallet (funds are locked here)
            $buyerWallet = $escrowLock->wallet;

            // Get seller's wallet
            $sellerWallet = $order->seller->wallet;

            // Deduct from buyer's locked funds
            $buyerWallet->decrement('locked_escrow_funds', $amount);

            // Add to seller's available balance (minus platform fee)
            $sellerWallet->increment('available_balance', $sellerPayout);

            // Mark escrow as released
            $escrowLock->update(['released_at' => now()]);

            // Create ledger entries
            DB::table('ledger_entries')->insert([
                [
                    'transaction_id' => Str::uuid(),
                    'wallet_id' => $buyerWallet->id,
                    'type' => 'DEBIT',
                    'amount' => $amount,
                    'description' => "Escrow released for Order #{$order->id}",
                    'reference_table' => 'orders',
                    'reference_id' => $order->id,
                    'created_at' => now(),
                ],
                [
                    'transaction_id' => Str::uuid(),
                    'wallet_id' => $sellerWallet->id,
                    'type' => 'CREDIT',
                    'amount' => $sellerPayout,
                    'description' => "Payment received for Order #{$order->id}",
                    'reference_table' => 'orders',
                    'reference_id' => $order->id,
                    'created_at' => now(),
                ],
            ]);

            // If there's a platform fee, record it
            if ($platformFee > 0) {
                DB::table('ledger_entries')->insert([
                    'transaction_id' => Str::uuid(),
                    'wallet_id' => $buyerWallet->id,
                    'type' => 'DEBIT',
                    'amount' => $platformFee,
                    'description' => "Platform fee for Order #{$order->id}",
                    'reference_table' => 'orders',
                    'reference_id' => $order->id,
                    'created_at' => now(),
                ]);
            }

            // Audit logs
            AuditService::log(
                'escrow.funds_released',
                "Released {$sellerPayout} to seller for order #{$order->id} (fee: {$platformFee})",
                $order,
                [],
                ['seller_payout' => $sellerPayout, 'platform_fee' => $platformFee],
                [
                    'order_id' => $order->id,
                    'buyer_id' => $order->buyer_id,
                    'seller_id' => $order->seller_id,
                ]
            );

            AuditService::logWalletCredited(
                $sellerWallet->id,
                $sellerPayout,
                $sellerWallet->available_balance - $sellerPayout,
                $sellerWallet->available_balance,
                "Order #{$order->id} completed"
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'escrow.release_failed',
                "Failed to release funds for order #{$order->id}",
                $e->getMessage(),
                ['order_id' => $order->id]
            );

            throw $e;
        }
    }

    /**
     * Refund funds from escrow to buyer
     */
    public function refundFunds(Order $order, EscrowLock $escrowLock, string $reason): void
    {
        DB::beginTransaction();

        try {
            if (!$escrowLock->isLocked()) {
                throw new \Exception('Escrow is not locked');
            }

            $amount = $escrowLock->amount;

            // Get buyer's wallet
            $buyerWallet = $escrowLock->wallet;

            // Deduct from locked funds
            $buyerWallet->decrement('locked_escrow_funds', $amount);

            // Add back to available balance
            $buyerWallet->increment('available_balance', $amount);

            // Mark escrow as refunded
            $escrowLock->update(['refunded_at' => now()]);

            // Create ledger entry
            DB::table('ledger_entries')->insert([
                'transaction_id' => Str::uuid(),
                'wallet_id' => $buyerWallet->id,
                'type' => 'CREDIT',
                'amount' => $amount,
                'description' => "Refund for Order #{$order->id} - {$reason}",
                'reference_table' => 'orders',
                'reference_id' => $order->id,
                'created_at' => now(),
            ]);

            // Audit log
            AuditService::log(
                'escrow.funds_refunded',
                "Refunded {$amount} to buyer for order #{$order->id}",
                $order,
                [],
                ['refund_amount' => $amount],
                [
                    'order_id' => $order->id,
                    'reason' => $reason,
                ]
            );

            AuditService::logWalletCredited(
                $buyerWallet->id,
                $amount,
                $buyerWallet->available_balance - $amount,
                $buyerWallet->available_balance,
                "Order #{$order->id} refund - {$reason}"
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'escrow.refund_failed',
                "Failed to refund funds for order #{$order->id}",
                $e->getMessage(),
                ['order_id' => $order->id, 'reason' => $reason]
            );

            throw $e;
        }
    }

    /**
     * Check if wallet has sufficient balance for order
     */
    public function hasSufficientBalance(Wallet $wallet, float $amount): bool
    {
        return $wallet->available_balance >= $amount;
    }

    /**
     * Calculate escrow details for an order
     */
    public function calculateEscrowDetails(Order $order): array
    {
        $price = $order->price;
        $platformFee = $order->getPlatformFee();
        $sellerPayout = $order->getSellerPayout();

        return [
            'order_price' => $price,
            'platform_fee' => $platformFee,
            'platform_fee_percentage' => config('escrow.platform_fee_percentage', 2.5),
            'seller_payout' => $sellerPayout,
        ];
    }
}
