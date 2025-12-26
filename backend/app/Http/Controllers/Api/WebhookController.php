<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /**
     * Handle Paystack webhook
     */
    public function paystackWebhook(Request $request)
    {
        // Verify webhook signature
        $signature = $request->header('x-paystack-signature');
        $body = $request->getContent();

        $expectedSignature = hash_hmac('sha512', $body, config('payments.gateways.paystack.secret_key'));

        if ($signature !== $expectedSignature) {
            Log::warning('Invalid Paystack webhook signature');
            
            AuditService::log(
                'webhook.paystack.signature_invalid',
                'Invalid Paystack webhook signature received',
                null,
                [],
                [],
                ['ip' => $request->ip()],
                'FAILED'
            );

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        // Log webhook received
        AuditService::logWebhookReceived('paystack', $event, $data);

        try {
            DB::beginTransaction();

            switch ($event) {
                case 'charge.success':
                    $this->handlePaystackChargeSuccess($data);
                    break;

                case 'transfer.success':
                    $this->handlePaystackTransferSuccess($data);
                    break;

                case 'transfer.failed':
                    $this->handlePaystackTransferFailed($data);
                    break;

                default:
                    Log::info('Unhandled Paystack webhook event: ' . $event);
            }

            DB::commit();

            return response()->json(['message' => 'Webhook processed successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Paystack webhook error: ' . $e->getMessage(), [
                'event' => $event,
                'data' => $data,
            ]);

            AuditService::logFailure(
                "webhook.paystack.{$event}.failed",
                'Paystack webhook processing failed',
                $e->getMessage(),
                ['event' => $event, 'reference' => $data['reference'] ?? null]
            );

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle Stripe webhook
     */
    public function stripeWebhook(Request $request)
    {
        $webhookSecret = config('payments.gateways.stripe.webhook_secret');
        $signature = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $webhookSecret
            );
        } catch (\Exception $e) {
            Log::warning('Invalid Stripe webhook signature: ' . $e->getMessage());
            
            AuditService::log(
                'webhook.stripe.signature_invalid',
                'Invalid Stripe webhook signature received',
                null,
                [],
                [],
                ['ip' => $request->ip()],
                'FAILED'
            );

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // Log webhook received
        AuditService::logWebhookReceived('stripe', $event->type, (array)$event->data->object);

        try {
            DB::beginTransaction();

            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handleStripePaymentSuccess($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handleStripePaymentFailed($event->data->object);
                    break;

                case 'payout.paid':
                    $this->handleStripePayoutSuccess($event->data->object);
                    break;

                case 'payout.failed':
                    $this->handleStripePayoutFailed($event->data->object);
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event: ' . $event->type);
            }

            DB::commit();

            return response()->json(['message' => 'Webhook processed successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Stripe webhook error: ' . $e->getMessage(), [
                'event_type' => $event->type,
            ]);

            AuditService::logFailure(
                "webhook.stripe.{$event->type}.failed",
                'Stripe webhook processing failed',
                $e->getMessage(),
                ['event_type' => $event->type]
            );

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle successful Paystack charge
     */
    protected function handlePaystackChargeSuccess($data)
    {
        $reference = $data['reference'];

        $transaction = DB::table('payment_transactions')
            ->where('gateway_reference', $reference)
            ->first();

        if (!$transaction || $transaction->status === 'COMPLETED') {
            return;
        }

        $amount = $data['amount'] / 100; // Convert from kobo
        $wallet = DB::table('wallets')->where('id', $transaction->wallet_id)->first();
        $oldBalance = $wallet->available_balance;

        // Update transaction
        DB::table('payment_transactions')
            ->where('id', $transaction->id)
            ->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'gateway_data' => json_encode($data),
                'updated_at' => now(),
            ]);

        // Credit wallet
        DB::table('wallets')
            ->where('id', $transaction->wallet_id)
            ->increment('available_balance', $amount);

        $newBalance = DB::table('wallets')->where('id', $transaction->wallet_id)->value('available_balance');

        // Create ledger entry
        DB::table('ledger_entries')->insert([
            'transaction_id' => Str::uuid(),
            'wallet_id' => $transaction->wallet_id,
            'type' => 'CREDIT',
            'amount' => $amount,
            'description' => 'Wallet funded via Paystack',
            'reference_table' => 'payment_transactions',
            'reference_id' => $transaction->id,
            'created_at' => now(),
        ]);

        // Audit log
        AuditService::logWalletCredited(
            $transaction->wallet_id,
            $amount,
            $oldBalance,
            $newBalance,
            'Paystack charge success webhook'
        );

        Log::info('Paystack charge successful', ['transaction_id' => $transaction->id]);
    }

    /**
     * Handle successful Paystack transfer
     */
    protected function handlePaystackTransferSuccess($data)
    {
        $reference = $data['reference'];

        $transaction = DB::table('payment_transactions')
            ->where('gateway_reference', $reference)
            ->first();

        if (!$transaction || $transaction->status === 'COMPLETED') {
            return;
        }

        // Update transaction status
        DB::table('payment_transactions')
            ->where('id', $transaction->id)
            ->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'gateway_data' => json_encode($data),
                'updated_at' => now(),
            ]);

        // Audit log
        AuditService::log(
            'payment.withdrawal.completed',
            "Withdrawal completed successfully via Paystack",
            null,
            [],
            ['status' => 'COMPLETED'],
            ['transaction_id' => $transaction->id, 'reference' => $reference]
        );

        Log::info('Paystack transfer successful', ['transaction_id' => $transaction->id]);
    }

    /**
     * Handle failed Paystack transfer
     */
    protected function handlePaystackTransferFailed($data)
    {
        $reference = $data['reference'];

        $transaction = DB::table('payment_transactions')
            ->where('gateway_reference', $reference)
            ->first();

        if (!$transaction) {
            return;
        }

        $wallet = DB::table('wallets')->where('id', $transaction->wallet_id)->first();
        $oldBalance = $wallet->available_balance;

        // Update transaction status
        DB::table('payment_transactions')
            ->where('id', $transaction->id)
            ->update([
                'status' => 'FAILED',
                'failure_reason' => $data['reason'] ?? 'Transfer failed',
                'gateway_data' => json_encode($data),
                'updated_at' => now(),
            ]);

        // Refund amount back to wallet
        $totalDeduction = $transaction->amount + $transaction->fee;

        DB::table('wallets')
            ->where('id', $transaction->wallet_id)
            ->increment('available_balance', $totalDeduction);

        $newBalance = DB::table('wallets')->where('id', $transaction->wallet_id)->value('available_balance');

        // Create refund ledger entry
        DB::table('ledger_entries')->insert([
            'transaction_id' => Str::uuid(),
            'wallet_id' => $transaction->wallet_id,
            'type' => 'CREDIT',
            'amount' => $totalDeduction,
            'description' => 'Withdrawal failed - amount refunded',
            'reference_table' => 'payment_transactions',
            'reference_id' => $transaction->id,
            'created_at' => now(),
        ]);

        // Audit log
        AuditService::logWalletCredited(
            $transaction->wallet_id,
            $totalDeduction,
            $oldBalance,
            $newBalance,
            'Paystack transfer failed - refund'
        );

        AuditService::logFailure(
            'payment.withdrawal.failed',
            'Paystack transfer failed - funds refunded',
            $data['reason'] ?? 'Unknown reason',
            ['transaction_id' => $transaction->id, 'reference' => $reference]
        );

        Log::warning('Paystack transfer failed', ['transaction_id' => $transaction->id]);
    }

    /**
     * Handle successful Stripe payment
     */
    protected function handleStripePaymentSuccess($paymentIntent)
    {
        $reference = $paymentIntent->id;

        $transaction = DB::table('payment_transactions')
            ->where('gateway_reference', $reference)
            ->first();

        if (!$transaction || $transaction->status === 'COMPLETED') {
            return;
        }

        $amount = $paymentIntent->amount / 100; // Convert from cents
        $wallet = DB::table('wallets')->where('id', $transaction->wallet_id)->first();
        $oldBalance = $wallet->available_balance;

        // Update transaction
        DB::table('payment_transactions')
            ->where('id', $transaction->id)
            ->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'gateway_data' => json_encode($paymentIntent),
                'updated_at' => now(),
            ]);

        // Credit wallet
        DB::table('wallets')
            ->where('id', $transaction->wallet_id)
            ->increment('available_balance', $amount);

        $newBalance = DB::table('wallets')->where('id', $transaction->wallet_id)->value('available_balance');

        // Create ledger entry
        DB::table('ledger_entries')->insert([
            'transaction_id' => Str::uuid(),
            'wallet_id' => $transaction->wallet_id,
            'type' => 'CREDIT',
            'amount' => $amount,
            'description' => 'Wallet funded via Stripe',
            'reference_table' => 'payment_transactions',
            'reference_id' => $transaction->id,
            'created_at' => now(),
        ]);

        // Audit log
        AuditService::logWalletCredited(
            $transaction->wallet_id,
            $amount,
            $oldBalance,
            $newBalance,
            'Stripe payment success webhook'
        );

        Log::info('Stripe payment successful', ['transaction_id' => $transaction->id]);
    }

    /**
     * Handle failed Stripe payment
     */
    protected function handleStripePaymentFailed($paymentIntent)
    {
        $reference = $paymentIntent->id;

        $transaction = DB::table('payment_transactions')
            ->where('gateway_reference', $reference)
            ->first();

        if (!$transaction) {
            return;
        }

        // Update transaction status
        DB::table('payment_transactions')
            ->where('id', $transaction->id)
            ->update([
                'status' => 'FAILED',
                'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Payment failed',
                'gateway_data' => json_encode($paymentIntent),
                'updated_at' => now(),
            ]);

        // Audit log
        AuditService::logFailure(
            'payment.deposit.failed',
            'Stripe payment failed',
            $paymentIntent->last_payment_error->message ?? 'Unknown error',
            ['transaction_id' => $transaction->id, 'reference' => $reference]
        );

        Log::warning('Stripe payment failed', ['transaction_id' => $transaction->id]);
    }

    /**
     * Handle successful Stripe payout
     */
    protected function handleStripePayoutSuccess($payout)
    {
        $reference = $payout->id;

        $transaction = DB::table('payment_transactions')
            ->where('gateway_reference', $reference)
            ->first();

        if (!$transaction || $transaction->status === 'COMPLETED') {
            return;
        }

        // Update transaction status
        DB::table('payment_transactions')
            ->where('id', $transaction->id)
            ->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'gateway_data' => json_encode($payout),
                'updated_at' => now(),
            ]);

        // Audit log
        AuditService::log(
            'payment.withdrawal.completed',
            "Withdrawal completed successfully via Stripe",
            null,
            [],
            ['status' => 'COMPLETED'],
            ['transaction_id' => $transaction->id, 'reference' => $reference]
        );

        Log::info('Stripe payout successful', ['transaction_id' => $transaction->id]);
    }

    /**
     * Handle failed Stripe payout
     */
    protected function handleStripePayoutFailed($payout)
    {
        $reference = $payout->id;

        $transaction = DB::table('payment_transactions')
            ->where('gateway_reference', $reference)
            ->first();

        if (!$transaction) {
            return;
        }

        $wallet = DB::table('wallets')->where('id', $transaction->wallet_id)->first();
        $oldBalance = $wallet->available_balance;

        // Update transaction status
        DB::table('payment_transactions')
            ->where('id', $transaction->id)
            ->update([
                'status' => 'FAILED',
                'failure_reason' => $payout->failure_message ?? 'Payout failed',
                'gateway_data' => json_encode($payout),
                'updated_at' => now(),
            ]);

        // Refund amount back to wallet
        $totalDeduction = $transaction->amount + $transaction->fee;

        DB::table('wallets')
            ->where('id', $transaction->wallet_id)
            ->increment('available_balance', $totalDeduction);

        $newBalance = DB::table('wallets')->where('id', $transaction->wallet_id)->value('available_balance');

        // Create refund ledger entry
        DB::table('ledger_entries')->insert([
            'transaction_id' => Str::uuid(),
            'wallet_id' => $transaction->wallet_id,
            'type' => 'CREDIT',
            'amount' => $totalDeduction,
            'description' => 'Withdrawal failed - amount refunded',
            'reference_table' => 'payment_transactions',
            'reference_id' => $transaction->id,
            'created_at' => now(),
        ]);

        // Audit log
        AuditService::logWalletCredited(
            $transaction->wallet_id,
            $totalDeduction,
            $oldBalance,
            $newBalance,
            'Stripe payout failed - refund'
        );

        AuditService::logFailure(
            'payment.withdrawal.failed',
            'Stripe payout failed - funds refunded',
            $payout->failure_message ?? 'Unknown reason',
            ['transaction_id' => $transaction->id, 'reference' => $reference]
        );

        Log::warning('Stripe payout failed', ['transaction_id' => $transaction->id]);
    }
}
