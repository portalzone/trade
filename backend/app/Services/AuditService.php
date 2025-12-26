<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an audit entry
     *
     * @param string $action - Action performed (e.g., 'payment.deposit.initiated')
     * @param string $description - Human-readable description
     * @param mixed $auditable - Model being audited (Order, Payment, etc.)
     * @param array $oldValues - State before action
     * @param array $newValues - State after action
     * @param array $metadata - Additional context
     * @param string $status - SUCCESS, FAILED, PENDING
     * @return void
     */
    public static function log(
        string $action,
        string $description,
        $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        string $status = 'SUCCESS'
    ): void {
        try {
            $user = auth()->user();

            DB::table('audit_logs')->insert([
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'action' => $action,
                'auditable_type' => $auditable ? get_class($auditable) : null,
                'auditable_id' => $auditable?->id ?? null,
                'description' => $description,
                'old_values' => !empty($oldValues) ? json_encode($oldValues) : null,
                'new_values' => !empty($newValues) ? json_encode($newValues) : null,
                'metadata' => !empty($metadata) ? json_encode($metadata) : null,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log audit failure but don't break the application
            \Log::error('Audit logging failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log payment deposit initiated
     */
    public static function logDepositInitiated(int $transactionId, float $amount, string $gateway, int $userId): void
    {
        self::log(
            action: 'payment.deposit.initiated',
            description: "User initiated deposit of {$amount} via {$gateway}",
            auditable: null,
            oldValues: [],
            newValues: [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'gateway' => $gateway,
                'status' => 'PENDING',
            ],
            metadata: [
                'user_id' => $userId,
                'gateway' => $gateway,
            ]
        );
    }

    /**
     * Log payment deposit verified
     */
    public static function logDepositVerified(int $transactionId, float $amount, float $oldBalance, float $newBalance): void
    {
        self::log(
            action: 'payment.deposit.verified',
            description: "Deposit verified and wallet credited with {$amount}",
            auditable: null,
            oldValues: [
                'wallet_balance' => $oldBalance,
            ],
            newValues: [
                'wallet_balance' => $newBalance,
                'amount_credited' => $amount,
            ],
            metadata: [
                'transaction_id' => $transactionId,
            ]
        );
    }

    /**
     * Log withdrawal initiated
     */
    public static function logWithdrawalInitiated(int $transactionId, float $amount, float $fee, string $gateway, array $bankDetails): void
    {
        self::log(
            action: 'payment.withdrawal.initiated',
            description: "User initiated withdrawal of {$amount} (fee: {$fee}) via {$gateway}",
            auditable: null,
            oldValues: [],
            newValues: [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'fee' => $fee,
                'total_deducted' => $amount + $fee,
                'status' => 'PROCESSING',
            ],
            metadata: [
                'gateway' => $gateway,
                'bank_account' => $bankDetails['account_number'] ?? null,
                'bank_code' => $bankDetails['bank_code'] ?? null,
            ]
        );
    }

    /**
     * Log webhook received
     */
    public static function logWebhookReceived(string $gateway, string $event, array $data, string $status = 'SUCCESS'): void
    {
        self::log(
            action: "webhook.{$gateway}.{$event}",
            description: "Webhook received from {$gateway}: {$event}",
            auditable: null,
            oldValues: [],
            newValues: [],
            metadata: [
                'gateway' => $gateway,
                'event' => $event,
                'reference' => $data['reference'] ?? null,
                'amount' => $data['amount'] ?? null,
            ],
            status: $status
        );
    }

    /**
     * Log wallet credited
     */
    public static function logWalletCredited(int $walletId, float $amount, float $oldBalance, float $newBalance, string $reason): void
    {
        self::log(
            action: 'wallet.credited',
            description: "Wallet credited with {$amount} - {$reason}",
            auditable: null,
            oldValues: [
                'available_balance' => $oldBalance,
            ],
            newValues: [
                'available_balance' => $newBalance,
                'amount_added' => $amount,
            ],
            metadata: [
                'wallet_id' => $walletId,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Log wallet debited
     */
    public static function logWalletDebited(int $walletId, float $amount, float $oldBalance, float $newBalance, string $reason): void
    {
        self::log(
            action: 'wallet.debited',
            description: "Wallet debited {$amount} - {$reason}",
            auditable: null,
            oldValues: [
                'available_balance' => $oldBalance,
            ],
            newValues: [
                'available_balance' => $newBalance,
                'amount_deducted' => $amount,
            ],
            metadata: [
                'wallet_id' => $walletId,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Log failed action
     */
    public static function logFailure(string $action, string $description, string $errorMessage, array $metadata = []): void
    {
        self::log(
            action: $action,
            description: $description,
            auditable: null,
            oldValues: [],
            newValues: [],
            metadata: array_merge($metadata, ['error' => $errorMessage]),
            status: 'FAILED'
        );
    }

    /**
     * Get audit logs for a user
     */
    public static function getUserLogs(int $userId, int $limit = 50): array
    {
        return DB::table('audit_logs')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get audit logs for a specific action
     */
    public static function getActionLogs(string $action, int $limit = 100): array
    {
        return DB::table('audit_logs')
            ->where('action', 'LIKE', $action . '%')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
