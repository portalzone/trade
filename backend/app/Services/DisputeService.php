<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class DisputeService
{
    protected EscrowService $escrowService;

    public function __construct(EscrowService $escrowService)
    {
        $this->escrowService = $escrowService;
    }

    /**
     * Resolve dispute in favor of buyer (full refund)
     */
    public function resolveInFavorOfBuyer(Dispute $dispute, string $adminNotes)
    {
        DB::beginTransaction();

        try {
            $order = $dispute->order;
            $escrowLock = $order->escrowLock;

            if (!$escrowLock || !$escrowLock->isLocked()) {
                throw new \Exception('Escrow is not locked');
            }

            // Refund buyer
            $this->escrowService->refundFunds(
                $order,
                $escrowLock,
                "Dispute resolved in favor of buyer"
            );

            // Update dispute
            $dispute->update([
                'dispute_status' => 'RESOLVED_BUYER',
                'admin_notes' => $adminNotes,
                'resolution_details' => 'Full refund issued to buyer',
                'resolved_at' => now(),
            ]);

            // Update order
            $order->update([
                'order_status' => 'CANCELLED',
                'cancelled_at' => now(),
                'cancellation_reason' => 'Dispute resolved - Refunded to buyer',
            ]);

            // Audit log
            AuditService::log(
                'dispute.resolved_buyer',
                "Dispute #{$dispute->id} resolved in favor of buyer",
                $dispute,
                ['status' => 'OPEN'],
                ['status' => 'RESOLVED_BUYER'],
                [
                    'order_id' => $order->id,
                    'admin_notes' => $adminNotes,
                ]
            );

            DB::commit();

            return $dispute->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'dispute.resolve_buyer_failed',
                "Failed to resolve dispute #{$dispute->id} in favor of buyer",
                $e->getMessage(),
                ['dispute_id' => $dispute->id]
            );

            throw $e;
        }
    }

    /**
     * Resolve dispute in favor of seller (full payment)
     */
    public function resolveInFavorOfSeller(Dispute $dispute, string $adminNotes)
    {
        DB::beginTransaction();

        try {
            $order = $dispute->order;
            $escrowLock = $order->escrowLock;

            if (!$escrowLock || !$escrowLock->isLocked()) {
                throw new \Exception('Escrow is not locked');
            }

            // Release funds to seller
            $this->escrowService->releaseFunds($order, $escrowLock);

            // Update dispute
            $dispute->update([
                'dispute_status' => 'RESOLVED_SELLER',
                'admin_notes' => $adminNotes,
                'resolution_details' => 'Full payment released to seller',
                'resolved_at' => now(),
            ]);

            // Update order
            $order->update([
                'order_status' => 'COMPLETED',
                'completed_at' => now(),
            ]);

            // Audit log
            AuditService::log(
                'dispute.resolved_seller',
                "Dispute #{$dispute->id} resolved in favor of seller",
                $dispute,
                ['status' => 'OPEN'],
                ['status' => 'RESOLVED_SELLER'],
                [
                    'order_id' => $order->id,
                    'admin_notes' => $adminNotes,
                ]
            );

            DB::commit();

            return $dispute->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'dispute.resolve_seller_failed',
                "Failed to resolve dispute #{$dispute->id} in favor of seller",
                $e->getMessage(),
                ['dispute_id' => $dispute->id]
            );

            throw $e;
        }
    }

    /**
     * Resolve with partial refund (custom split)
     */
    public function resolvePartialRefund(
        Dispute $dispute,
        float $buyerAmount,
        float $sellerAmount,
        string $adminNotes
    ) {
        DB::beginTransaction();

        try {
            $order = $dispute->order;
            $escrowLock = $order->escrowLock;

            if (!$escrowLock || !$escrowLock->isLocked()) {
                throw new \Exception('Escrow is not locked');
            }

            $totalLocked = (float) $escrowLock->amount;
            $platformFee = (float) $escrowLock->platform_fee;

            // Validate amounts (must equal locked amount minus platform fee)
            $totalDistribution = $buyerAmount + $sellerAmount;
            if (abs($totalDistribution - $totalLocked) > 0.01) {
                throw new \Exception("Amounts must sum to locked amount: ₦{$totalLocked}. Got: ₦{$totalDistribution}");
            }

            $buyerWallet = $escrowLock->wallet;
            $sellerWallet = $order->seller->wallet;

            // Deduct from buyer's locked funds
            $buyerWallet->decrement('locked_escrow_funds', $totalLocked);

            // Refund buyer portion
            if ($buyerAmount > 0) {
                $buyerWallet->increment('available_balance', $buyerAmount);

                // Create ledger entry
                DB::table('ledger_entries')->insert([
                    'transaction_id' => \Illuminate\Support\Str::uuid(),
                    'wallet_id' => $buyerWallet->id,
                    'type' => 'CREDIT',
                    'amount' => $buyerAmount,
                    'description' => "Partial refund for Order #{$order->id} - Dispute resolution",
                    'reference_table' => 'disputes',
                    'reference_id' => $dispute->id,
                    'created_at' => now(),
                ]);
            }

            // Pay seller portion
            if ($sellerAmount > 0) {
                $sellerWallet->increment('available_balance', $sellerAmount);

                // Create ledger entry
                DB::table('ledger_entries')->insert([
                    'transaction_id' => \Illuminate\Support\Str::uuid(),
                    'wallet_id' => $sellerWallet->id,
                    'type' => 'CREDIT',
                    'amount' => $sellerAmount,
                    'description' => "Partial payment for Order #{$order->id} - Dispute resolution",
                    'reference_table' => 'disputes',
                    'reference_id' => $dispute->id,
                    'created_at' => now(),
                ]);
            }

            // Mark escrow as released
            $escrowLock->update(['released_at' => now()]);

            // Update dispute
            $dispute->update([
                'dispute_status' => 'RESOLVED_REFUND',
                'admin_notes' => $adminNotes,
                'resolution_details' => "Partial refund: Buyer ₦{$buyerAmount}, Seller ₦{$sellerAmount}",
                'resolved_at' => now(),
            ]);

            // Update order
            $order->update([
                'order_status' => 'CANCELLED',
                'cancelled_at' => now(),
                'cancellation_reason' => 'Dispute resolved - Partial refund',
            ]);

            // Audit log
            AuditService::log(
                'dispute.resolved_partial',
                "Dispute #{$dispute->id} resolved with partial refund",
                $dispute,
                ['status' => $order->getOriginal('order_status')],
                ['status' => 'CANCELLED'],
                [
                    'order_id' => $order->id,
                    'buyer_amount' => $buyerAmount,
                    'seller_amount' => $sellerAmount,
                    'admin_notes' => $adminNotes,
                ]
            );

            DB::commit();

            return $dispute->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'dispute.resolve_partial_failed',
                "Failed to resolve dispute #{$dispute->id} with partial refund",
                $e->getMessage(),
                [
                    'dispute_id' => $dispute->id,
                    'buyer_amount' => $buyerAmount,
                    'seller_amount' => $sellerAmount,
                ]
            );

            throw $e;
        }
    }
}
