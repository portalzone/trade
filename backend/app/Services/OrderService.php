<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Dispute;
use Illuminate\Support\Facades\DB;

class OrderService
{
    protected EscrowService $escrowService;
    protected TransactionLimitService $limitService;

    public function __construct(EscrowService $escrowService, TransactionLimitService $limitService)
    {
        $this->escrowService = $escrowService;
        $this->limitService = $limitService;
    }

    /**
     * Create a new order
     */
    public function createOrder(User $seller, array $data): Order
    {
        DB::beginTransaction();

        try {
            // Validate seller has a wallet
            if (!$seller->wallet) {
                throw new \Exception('Seller does not have a wallet');
            }

            $order = Order::create([
                'seller_id' => $seller->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'price' => $data['price'],
                'currency' => $data['currency'] ?? 'NGN',
                'category' => $data['category'] ?? null,
                'images' => $data['images'] ?? null,
                'order_status' => 'ACTIVE',
            ]);

            // Audit log
            AuditService::log(
                'order.created',
                "Created order: {$order->title}",
                $order,
                [],
                [
                    'order_id' => $order->id,
                    'price' => $order->price,
                    'currency' => $order->currency,
                ],
                ['seller_id' => $seller->id]
            );

            DB::commit();

            return $order;
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'order.create_failed',
                'Failed to create order',
                $e->getMessage(),
                ['seller_id' => $seller->id, 'data' => $data]
            );

            throw $e;
        }
    }

    /**
     * Purchase an order (lock funds in escrow)
     */
    public function purchaseOrder(Order $order, User $buyer): Order
    {
        DB::beginTransaction();

        try {
            // Validations
            if (!$order->isAvailable()) {
                throw new \Exception('Order is not available for purchase');
            }

            if ($order->seller_id === $buyer->id) {
                throw new \Exception('Cannot purchase your own order');
            }

            if (!$buyer->wallet) {
                throw new \Exception('Buyer does not have a wallet');
            }

            // ✅ NEW: Check transaction limits
            $limitCheck = $this->limitService->canTransact($buyer, $order->price);
            if (!$limitCheck['allowed']) {
                throw new \Exception($limitCheck['message']);
            }

            // Check balance
            if (!$this->escrowService->hasSufficientBalance($buyer->wallet, $order->price)) {
                throw new \Exception('Insufficient wallet balance');
            }

            // Lock funds in escrow
            $escrowLock = $this->escrowService->lockFunds($order, $buyer->wallet);

            // Update order
            $order->update([
                'buyer_id' => $buyer->id,
                'order_status' => 'IN_ESCROW',
                'escrow_locked_at' => now(),
            ]);

            // Audit log
            AuditService::log(
                'order.purchased',
                "Order #{$order->id} purchased by buyer",
                $order,
                ['status' => 'ACTIVE'],
                ['status' => 'IN_ESCROW', 'buyer_id' => $buyer->id],
                [
                    'order_id' => $order->id,
                    'buyer_id' => $buyer->id,
                    'seller_id' => $order->seller_id,
                    'amount' => $order->price,
                ]
            );

            DB::commit();
            
            // Send email notifications
            $order->seller->notify(new \App\Notifications\OrderPurchasedNotification($order, 'seller'));
            $order->buyer->notify(new \App\Notifications\OrderPurchasedNotification($order, 'buyer'));

            return $order->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'order.purchase_failed',
                "Failed to purchase order #{$order->id}",
                $e->getMessage(),
                ['order_id' => $order->id, 'buyer_id' => $buyer->id]
            );

            throw $e;
        }
    }

    /**
     * Complete an order (release funds to seller)
     */
    public function completeOrder(Order $order, User $user): Order
    {
        DB::beginTransaction();

        try {
            // Validations
            if (!$order->isInEscrow()) {
                throw new \Exception('Order is not in escrow');
            }

            if ($order->buyer_id !== $user->id) {
                throw new \Exception('Only buyer can complete the order');
            }

            // Get escrow lock
            $escrowLock = $order->escrowLock;

            if (!$escrowLock) {
                throw new \Exception('Escrow lock not found');
            }

            // Release funds to seller
            $this->escrowService->releaseFunds($order, $escrowLock);

            // Update order
            $order->update([
                'order_status' => 'COMPLETED',
                'completed_at' => now(),
            ]);

            // Audit log
            AuditService::log(
                'order.completed',
                "Order #{$order->id} completed successfully",
                $order,
                ['status' => 'IN_ESCROW'],
                ['status' => 'COMPLETED'],
                [
                    'order_id' => $order->id,
                    'buyer_id' => $order->buyer_id,
                    'seller_id' => $order->seller_id,
                ]
            );

            DB::commit();
            
            // Send email notifications
            $order->seller->notify(new \App\Notifications\OrderPurchasedNotification($order, 'seller'));
            $order->buyer->notify(new \App\Notifications\OrderPurchasedNotification($order, 'buyer'));

            return $order->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'order.complete_failed',
                "Failed to complete order #{$order->id}",
                $e->getMessage(),
                ['order_id' => $order->id, 'user_id' => $user->id]
            );

            throw $e;
        }
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(Order $order, User $user, string $reason): Order
    {
        DB::beginTransaction();

        try {
            if (!$order->canBeCancelled()) {
                throw new \Exception('Order cannot be cancelled');
            }

            // If order is ACTIVE (no buyer yet)
            if ($order->order_status === 'ACTIVE') {
                // Only seller can cancel
                if ($order->seller_id !== $user->id) {
                    throw new \Exception('Only seller can cancel active order');
                }

                $order->update([
                    'order_status' => 'CANCELLED',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);
            }
            // If order is IN_ESCROW
            elseif ($order->order_status === 'IN_ESCROW') {
                // Get escrow lock
                $escrowLock = $order->escrowLock;

                if (!$escrowLock) {
                    throw new \Exception('Escrow lock not found');
                }

                // Refund buyer
                $this->escrowService->refundFunds($order, $escrowLock, $reason);

                $order->update([
                    'order_status' => 'CANCELLED',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);
            }

            // Audit log
            AuditService::log(
                'order.cancelled',
                "Order #{$order->id} cancelled: {$reason}",
                $order,
                ['status' => $order->getOriginal('order_status')],
                ['status' => 'CANCELLED'],
                [
                    'order_id' => $order->id,
                    'cancelled_by' => $user->id,
                    'reason' => $reason,
                ]
            );

            DB::commit();

            return $order->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'order.cancel_failed',
                "Failed to cancel order #{$order->id}",
                $e->getMessage(),
                ['order_id' => $order->id, 'user_id' => $user->id, 'reason' => $reason]
            );

            throw $e;
        }
    }

    /**
     * Raise a dispute for an order
     */
    public function raiseDispute(Order $order, User $user, string $reason): Dispute
    {
        DB::beginTransaction();

        try {
            if (!$order->canBeDisputed()) {
                throw new \Exception('Order cannot be disputed');
            }

            // Validate user is buyer or seller
            if ($order->buyer_id !== $user->id && $order->seller_id !== $user->id) {
                throw new \Exception('Only buyer or seller can raise dispute');
            }

            // Create dispute
            $dispute = Dispute::create([
                'order_id' => $order->id,
                'raised_by_user_id' => $user->id,
                'dispute_reason' => $reason,
                'dispute_status' => 'OPEN',
            ]);

            // Update order status
            $order->update(['order_status' => 'DISPUTED']);

            // Audit log
            AuditService::log(
                'dispute.raised',
                "Dispute raised for order #{$order->id}",
                $dispute,
                [],
                ['dispute_id' => $dispute->id, 'order_status' => 'DISPUTED'],
                [
                    'order_id' => $order->id,
                    'raised_by' => $user->id,
                    'reason' => $reason,
                ]
            );

            DB::commit();

            // Send email notifications
            $order->seller->notify(new \App\Notifications\OrderPurchasedNotification($order, 'seller'));
            $order->buyer->notify(new \App\Notifications\OrderPurchasedNotification($order, 'buyer'));

            // Load relationships with correct column names
            return $dispute->load([
                'order',
                'raisedBy:id,full_name,email'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'dispute.raise_failed',
                "Failed to raise dispute for order #{$order->id}",
                $e->getMessage(),
                ['order_id' => $order->id, 'user_id' => $user->id]
            );

            throw $e;
        }
    }

    /**
     * Update an order
     */
    public function updateOrder(Order $order, User $seller, array $data): Order
    {
        // Can only update ACTIVE orders with no buyer
        if ($order->order_status !== 'ACTIVE' || !is_null($order->buyer_id)) {
            throw new \Exception('Order cannot be updated');
        }

        if ($order->seller_id !== $seller->id) {
            throw new \Exception('Only seller can update order');
        }

        $order->update(array_filter([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'price' => $data['price'] ?? null,
            'category' => $data['category'] ?? null,
            'images' => $data['images'] ?? null,
        ]));

        return $order->fresh();
    }

    /**
     * Delete an order
     */
    public function deleteOrder(Order $order, User $seller): bool
    {
        // Can only delete ACTIVE orders with no buyer
        if ($order->order_status !== 'ACTIVE' || !is_null($order->buyer_id)) {
            throw new \Exception('Order cannot be deleted');
        }

        if ($order->seller_id !== $seller->id) {
            throw new \Exception('Only seller can delete order');
        }

        return $order->delete();
    }

    /**
     * Auto-complete order after X days (system action)
     */
    public function autoCompleteOrder(Order $order): Order
    {
        DB::beginTransaction();

        try {
            if (!$order->isInEscrow()) {
                throw new \Exception('Order is not in escrow');
            }

            $escrowLock = $order->escrowLock;

            if (!$escrowLock) {
                throw new \Exception('Escrow lock not found');
            }

            $autoCompleteDays = config('escrow.auto_complete_days', 7);

            // Release funds to seller
            $this->escrowService->releaseFunds($order, $escrowLock);

            // Update order
            $order->update([
                'order_status' => 'COMPLETED',
                'completed_at' => now(),
            ]);

            // Audit log
            AuditService::log(
                'order.auto_completed',
                "Order #{$order->id} auto-completed after {$autoCompleteDays} days",
                $order,
                ['status' => 'IN_ESCROW'],
                ['status' => 'COMPLETED'],
                [
                    'order_id' => $order->id,
                    'reason' => 'Auto-completed by system',
                    'days_in_escrow' => $autoCompleteDays,
                ]
            );

            DB::commit();

            return $order->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'order.auto_complete_failed',
                "Failed to auto-complete order #{$order->id}",
                $e->getMessage(),
                ['order_id' => $order->id]
            );

            throw $e;
        }
    }
}
