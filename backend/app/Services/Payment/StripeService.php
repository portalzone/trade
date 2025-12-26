<?php

namespace App\Services\Payment;

use App\Models\User;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Str;

class StripeService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('payments.gateways.stripe.secret_key'));
    }

    /**
     * Initialize a payment intent
     */
    public function initializePayment(User $user, float $amount, array $metadata = []): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => 'usd',
                'customer' => $this->getOrCreateCustomer($user),
                'metadata' => array_merge([
                    'user_id' => $user->id,
                    'wallet_id' => $user->wallet->id,
                ], $metadata),
                'description' => 'Wallet funding',
            ]);

            return [
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amount,
            ];
        } catch (ApiErrorException $e) {
            throw new \Exception('Stripe initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Verify a payment intent
     */
    public function verifyPayment(string $paymentIntentId): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                throw new \Exception('Payment not successful: ' . $paymentIntent->status);
            }

            return [
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount / 100, // Convert from cents
                'currency' => strtoupper($paymentIntent->currency),
                'payment_intent_id' => $paymentIntent->id,
                'created_at' => date('Y-m-d H:i:s', $paymentIntent->created),
                'metadata' => $paymentIntent->metadata->toArray(),
            ];
        } catch (ApiErrorException $e) {
            throw new \Exception('Stripe verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a payout (withdrawal)
     */
    public function initiatePayout(User $user, float $amount, array $bankDetails): array
    {
        try {
            // First, create or get bank account
            $bankAccount = $this->addBankAccount($user, $bankDetails);

            // Create payout
            $payout = $this->stripe->payouts->create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => 'usd',
                'destination' => $bankAccount['id'],
                'metadata' => [
                    'user_id' => $user->id,
                    'wallet_id' => $user->wallet->id,
                ],
            ]);

            return [
                'payout_id' => $payout->id,
                'status' => $payout->status,
                'amount' => $amount,
                'arrival_date' => date('Y-m-d', $payout->arrival_date),
            ];
        } catch (ApiErrorException $e) {
            throw new \Exception('Stripe payout failed: ' . $e->getMessage());
        }
    }

    /**
     * Get or create Stripe customer
     */
    protected function getOrCreateCustomer(User $user): string
    {
        // Check if user has Stripe customer ID (you'll need to add this column later)
        // For now, create a new customer each time or search by email

        try {
            $customers = $this->stripe->customers->all([
                'email' => $user->email,
                'limit' => 1,
            ]);

            if (count($customers->data) > 0) {
                return $customers->data[0]->id;
            }

            // Create new customer
            $customer = $this->stripe->customers->create([
                'email' => $user->email,
                'name' => $user->full_name,
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            return $customer->id;
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to create Stripe customer: ' . $e->getMessage());
        }
    }

    /**
     * Add bank account for payouts
     */
    protected function addBankAccount(User $user, array $bankDetails): array
    {
        try {
            $customerId = $this->getOrCreateCustomer($user);

            $bankAccount = $this->stripe->customers->createSource(
                $customerId,
                [
                    'source' => [
                        'object' => 'bank_account',
                        'country' => 'US',
                        'currency' => 'usd',
                        'account_holder_name' => $user->full_name,
                        'account_holder_type' => 'individual',
                        'routing_number' => $bankDetails['routing_number'],
                        'account_number' => $bankDetails['account_number'],
                    ],
                ]
            );

            return [
                'id' => $bankAccount->id,
                'last4' => $bankAccount->last4,
                'bank_name' => $bankAccount->bank_name,
            ];
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to add bank account: ' . $e->getMessage());
        }
    }

    /**
     * Verify bank account (for US accounts)
     */
    public function verifyBankAccount(string $customerId, string $bankAccountId, array $amounts): bool
    {
        try {
            $this->stripe->customers->verifySource(
                $customerId,
                $bankAccountId,
                ['amounts' => $amounts]
            );

            return true;
        } catch (ApiErrorException $e) {
            throw new \Exception('Bank account verification failed: ' . $e->getMessage());
        }
    }
}
