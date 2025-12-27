<?php

namespace App\Services;

use App\Models\User;
use App\Models\PaymentLink;
use App\Models\PaymentLinkPayment;
use Illuminate\Support\Facades\DB;

class PaymentLinkService
{
    /**
     * Create payment link
     */
    public function createPaymentLink(User $user, array $data): PaymentLink
    {
        DB::beginTransaction();

        try {
            $paymentLink = PaymentLink::create([
                'user_id' => $user->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'NGN',
                'slug' => PaymentLink::generateSlug(),
                'status' => 'active',
                'max_uses' => $data['max_uses'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ]);

            // Audit log
            AuditService::log(
                'payment_link.created',
                "Payment link created: {$paymentLink->title}",
                $paymentLink,
                [],
                ['amount' => $paymentLink->amount],
                ['user_id' => $user->id]
            );

            DB::commit();

            return $paymentLink;
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'payment_link.create_failed',
                'Failed to create payment link',
                $e->getMessage(),
                ['user_id' => $user->id, 'data' => $data]
            );

            throw $e;
        }
    }

    /**
     * Process payment via link
     */
    public function processPayment(PaymentLink $paymentLink, array $data): PaymentLinkPayment
    {
        DB::beginTransaction();

        try {
            // Validate payment link is active
            if (!$paymentLink->isActive()) {
                throw new \Exception('Payment link is not active or has expired');
            }

            // Get payer (authenticated user or guest)
            $payer = $data['payer'] ?? null;

            // Create payment record
            $payment = PaymentLinkPayment::create([
                'payment_link_id' => $paymentLink->id,
                'payer_id' => $payer?->id,
                'payer_name' => $data['payer_name'] ?? $payer?->full_name,
                'payer_email' => $data['payer_email'] ?? $payer?->email,
                'payer_phone' => $data['payer_phone'] ?? $payer?->phone,
                'amount' => $paymentLink->amount,
                'reference' => PaymentLinkPayment::generateReference(),
                'status' => 'pending',
            ]);

            // If payer is authenticated and has wallet, process immediately
            if ($payer && $payer->wallet) {
                if ($payer->wallet->available_balance < $paymentLink->amount) {
                    throw new \Exception('Insufficient wallet balance');
                }

                // Deduct from payer's wallet
                $payer->wallet->decrement('available_balance', $paymentLink->amount);

                // Credit to payment link owner's wallet
                $paymentLink->user->wallet->increment('available_balance', $paymentLink->amount);

                // Mark as completed
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                ]);

                // Increment payment link usage
                $paymentLink->incrementUses();
            }

            // Audit log
            AuditService::log(
                'payment_link.payment_created',
                "Payment created for link: {$paymentLink->title}",
                $payment,
                [],
                ['amount' => $payment->amount, 'status' => $payment->status],
                ['payment_link_id' => $paymentLink->id]
            );

            DB::commit();

            return $payment->load('paymentLink');
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'payment_link.payment_failed',
                "Failed to process payment for link #{$paymentLink->id}",
                $e->getMessage(),
                ['payment_link_id' => $paymentLink->id, 'data' => $data]
            );

            throw $e;
        }
    }

    /**
     * Update payment link
     */
    public function updatePaymentLink(PaymentLink $paymentLink, User $user, array $data): PaymentLink
    {
        if ($paymentLink->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }

        $paymentLink->update(array_filter([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'] ?? null,
            'status' => $data['status'] ?? null,
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]));

        return $paymentLink->fresh();
    }

    /**
     * Delete payment link
     */
    public function deletePaymentLink(PaymentLink $paymentLink, User $user): bool
    {
        if ($paymentLink->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }

        // Can't delete if has completed payments
        if ($paymentLink->payments()->where('status', 'completed')->exists()) {
            throw new \Exception('Cannot delete payment link with completed payments');
        }

        return $paymentLink->delete();
    }

    /**
     * Get payment link stats
     */
    public function getStats(PaymentLink $paymentLink): array
    {
        $payments = $paymentLink->payments();

        return [
            'total_payments' => $payments->count(),
            'completed_payments' => $payments->where('status', 'completed')->count(),
            'pending_payments' => $payments->where('status', 'pending')->count(),
            'failed_payments' => $payments->where('status', 'failed')->count(),
            'total_amount_received' => $payments->where('status', 'completed')->sum('amount'),
            'current_uses' => $paymentLink->current_uses,
            'max_uses' => $paymentLink->max_uses,
            'remaining_uses' => $paymentLink->max_uses ? ($paymentLink->max_uses - $paymentLink->current_uses) : null,
            'is_active' => $paymentLink->isActive(),
            'expires_at' => $paymentLink->expires_at,
        ];
    }
}
