<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaystackService;
use App\Services\Payment\StripeService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected PaystackService $paystack;
    protected StripeService $stripe;

    public function __construct(PaystackService $paystack, StripeService $stripe)
    {
        $this->paystack = $paystack;
        $this->stripe = $stripe;
    }

    /**
     * Initialize deposit (funding wallet)
     */
    public function initiateDeposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:' . config('payments.min_deposit'),
            'gateway' => 'required|in:paystack,stripe',
            'currency' => 'required|in:NGN,USD',
        ]);

        if ($validator->fails()) {
            AuditService::logFailure(
                'payment.deposit.validation_failed',
                'Deposit validation failed',
                json_encode($validator->errors()),
                ['amount' => $request->amount, 'gateway' => $request->gateway]
            );

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $amount = $request->amount;
        $gateway = $request->gateway;

        try {
            DB::beginTransaction();

            // Create pending transaction record
            $transaction = DB::table('payment_transactions')->insertGetId([
                'user_id' => $user->id,
                'wallet_id' => $user->wallet->id,
                'type' => 'DEPOSIT',
                'gateway' => strtoupper($gateway),
                'amount' => $amount,
                'currency' => $request->currency,
                'status' => 'PENDING',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Log deposit initiated
            AuditService::logDepositInitiated($transaction, $amount, $gateway, $user->id);

            // Initialize payment based on gateway
            if ($gateway === 'paystack') {
                $payment = $this->paystack->initializePayment($user, $amount, [
                    'transaction_id' => $transaction,
                ]);

                DB::table('payment_transactions')
                    ->where('id', $transaction)
                    ->update([
                        'gateway_reference' => $payment['reference'],
                        'gateway_data' => json_encode($payment),
                    ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Payment initialized successfully',
                    'data' => [
                        'transaction_id' => $transaction,
                        'authorization_url' => $payment['authorization_url'],
                        'reference' => $payment['reference'],
                    ],
                ]);
            } else {
                $payment = $this->stripe->initializePayment($user, $amount, [
                    'transaction_id' => $transaction,
                ]);

                DB::table('payment_transactions')
                    ->where('id', $transaction)
                    ->update([
                        'gateway_reference' => $payment['payment_intent_id'],
                        'gateway_data' => json_encode($payment),
                    ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Payment initialized successfully',
                    'data' => [
                        'transaction_id' => $transaction,
                        'client_secret' => $payment['client_secret'],
                        'payment_intent_id' => $payment['payment_intent_id'],
                    ],
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'payment.deposit.initialization_failed',
                'Failed to initialize deposit',
                $e->getMessage(),
                ['amount' => $amount, 'gateway' => $gateway, 'user_id' => $user->id]
            );

            return response()->json([
                'success' => false,
                'message' => 'Payment initialization failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify and complete deposit
     */
    public function verifyDeposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string',
            'gateway' => 'required|in:paystack,stripe',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $gateway = $request->gateway;
        $reference = $request->reference;

        try {
            DB::beginTransaction();

            // Verify payment with gateway
            if ($gateway === 'paystack') {
                $verification = $this->paystack->verifyPayment($reference);
            } else {
                $verification = $this->stripe->verifyPayment($reference);
            }

            // Find transaction
            $transaction = DB::table('payment_transactions')
                ->where('gateway_reference', $reference)
                ->first();

            if (!$transaction) {
                throw new \Exception('Transaction not found');
            }

            if ($transaction->status === 'COMPLETED') {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already verified',
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'amount' => $transaction->amount,
                    ],
                ]);
            }

            // Get old balance
            $wallet = DB::table('wallets')->where('id', $transaction->wallet_id)->first();
            $oldBalance = $wallet->available_balance;

            // Update transaction status
            DB::table('payment_transactions')
                ->where('id', $transaction->id)
                ->update([
                    'status' => 'COMPLETED',
                    'completed_at' => now(),
                    'gateway_data' => json_encode($verification),
                    'updated_at' => now(),
                ]);

            // Credit user wallet
            DB::table('wallets')
                ->where('id', $transaction->wallet_id)
                ->increment('available_balance', $verification['amount']);

            // Get new balance
            $newBalance = DB::table('wallets')->where('id', $transaction->wallet_id)->value('available_balance');

            // Create ledger entry
            DB::table('ledger_entries')->insert([
                'transaction_id' => Str::uuid(),
                'wallet_id' => $transaction->wallet_id,
                'type' => 'CREDIT',
                'amount' => $verification['amount'],
                'description' => 'Wallet funded via ' . $gateway,
                'reference_table' => 'payment_transactions',
                'reference_id' => $transaction->id,
                'created_at' => now(),
            ]);

            // Log deposit verified
            AuditService::logDepositVerified(
                $transaction->id,
                $verification['amount'],
                $oldBalance,
                $newBalance
            );

            AuditService::logWalletCredited(
                $transaction->wallet_id,
                $verification['amount'],
                $oldBalance,
                $newBalance,
                "Deposit via {$gateway}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment verified and wallet credited successfully',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'amount' => $verification['amount'],
                    'new_balance' => $newBalance,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'payment.deposit.verification_failed',
                'Failed to verify deposit',
                $e->getMessage(),
                ['reference' => $reference, 'gateway' => $gateway]
            );

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Initiate withdrawal
     */
    public function initiateWithdrawal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:' . config('payments.min_withdrawal'),
            'gateway' => 'required|in:paystack,stripe',
            'bank_details' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $amount = $request->amount;
        $gateway = $request->gateway;
        $bankDetails = $request->bank_details;

        // Check wallet balance
        $wallet = DB::table('wallets')->where('user_id', $user->id)->first();
        $oldBalance = $wallet->available_balance;

        if ($wallet->available_balance < $amount) {
            AuditService::logFailure(
                'payment.withdrawal.insufficient_balance',
                'Withdrawal failed - insufficient balance',
                "Attempted {$amount}, available {$wallet->available_balance}",
                ['user_id' => $user->id, 'amount' => $amount]
            );

            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Calculate fees
            $fee = config('payments.fees.withdrawal.flat_fee') + 
                   ($amount * config('payments.fees.withdrawal.percentage'));
            $totalDeduction = $amount + $fee;

            // Create withdrawal transaction
            $transaction = DB::table('payment_transactions')->insertGetId([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => 'WITHDRAWAL',
                'gateway' => strtoupper($gateway),
                'amount' => $amount,
                'fee' => $fee,
                'currency' => 'NGN',
                'status' => 'PENDING',
                'bank_details' => json_encode($bankDetails),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Initiate transfer with gateway
            if ($gateway === 'paystack') {
                $transfer = $this->paystack->initiateTransfer($user, $amount, $bankDetails);
            } else {
                $transfer = $this->stripe->initiatePayout($user, $amount, $bankDetails);
            }

            // Update transaction
            DB::table('payment_transactions')
                ->where('id', $transaction)
                ->update([
                    'gateway_reference' => $transfer['reference'] ?? $transfer['payout_id'],
                    'gateway_data' => json_encode($transfer),
                    'status' => 'PROCESSING',
                ]);

            // Deduct from wallet
            DB::table('wallets')
                ->where('id', $wallet->id)
                ->decrement('available_balance', $totalDeduction);

            $newBalance = DB::table('wallets')->where('id', $wallet->id)->value('available_balance');

            // Create ledger entries
            DB::table('ledger_entries')->insert([
                [
                    'transaction_id' => Str::uuid(),
                    'wallet_id' => $wallet->id,
                    'type' => 'DEBIT',
                    'amount' => $amount,
                    'description' => 'Withdrawal to bank account',
                    'reference_table' => 'payment_transactions',
                    'reference_id' => $transaction,
                    'created_at' => now(),
                ],
                [
                    'transaction_id' => Str::uuid(),
                    'wallet_id' => $wallet->id,
                    'type' => 'DEBIT',
                    'amount' => $fee,
                    'description' => 'Withdrawal fee',
                    'reference_table' => 'payment_transactions',
                    'reference_id' => $transaction,
                    'created_at' => now(),
                ],
            ]);

            // Log withdrawal
            AuditService::logWithdrawalInitiated(
                $transaction,
                $amount,
                $fee,
                $gateway,
                $bankDetails
            );

            AuditService::logWalletDebited(
                $wallet->id,
                $totalDeduction,
                $oldBalance,
                $newBalance,
                "Withdrawal via {$gateway}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal initiated successfully',
                'data' => [
                    'transaction_id' => $transaction,
                    'amount' => $amount,
                    'fee' => $fee,
                    'total_deducted' => $totalDeduction,
                    'status' => 'PROCESSING',
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'payment.withdrawal.failed',
                'Withdrawal failed',
                $e->getMessage(),
                ['amount' => $amount, 'gateway' => $gateway, 'user_id' => $user->id]
            );

            return response()->json([
                'success' => false,
                'message' => 'Withdrawal failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment transaction history
     */
    public function getTransactions(Request $request)
    {
        $user = $request->user();

        $transactions = DB::table('payment_transactions')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Get banks (for Paystack)
     */
    public function getBanks()
    {
        try {
            $banks = $this->paystack->listBanks();

            return response()->json([
                'success' => true,
                'data' => $banks,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch banks',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify bank account (for Paystack)
     */
    public function verifyBankAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_number' => 'required|string|size:10',
            'bank_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $verification = $this->paystack->verifyBankAccount(
                $request->account_number,
                $request->bank_code
            );

            return response()->json([
                'success' => true,
                'data' => $verification,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account verification failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
