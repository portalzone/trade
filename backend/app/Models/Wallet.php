<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'currency',
        'available_balance',
        'locked_escrow_funds',
        'wallet_status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'available_balance' => 'decimal:2',
        'locked_escrow_funds' => 'decimal:2',
        'total_balance' => 'decimal:2',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['total_balance'];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Get the user that owns the wallet (1:1)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all ledger entries for this wallet
     */
    public function ledgerEntries()
    {
        return $this->hasMany(LedgerEntry::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Get the total balance (available + locked)
     * Note: This is also computed in the database, but included for model consistency
     */
    public function getTotalBalanceAttribute(): float
    {
        return $this->available_balance + $this->locked_escrow_funds;
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope to filter active wallets
     */
    public function scopeActive($query)
    {
        return $query->where('wallet_status', 'ACTIVE');
    }

    /**
     * Scope to filter frozen wallets
     */
    public function scopeFrozen($query)
    {
        return $query->where('wallet_status', 'FROZEN');
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if wallet has sufficient available balance
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->available_balance >= $amount;
    }

    /**
     * Check if wallet is active
     */
    public function isActive(): bool
    {
        return $this->wallet_status === 'ACTIVE';
    }

    /**
     * Check if wallet is frozen
     */
    public function isFrozen(): bool
    {
        return $this->wallet_status === 'FROZEN';
    }

    /**
     * Freeze the wallet (prevents withdrawals and new transactions)
     */
    public function freeze(): bool
    {
        return $this->update(['wallet_status' => 'FROZEN']);
    }

    /**
     * Unfreeze the wallet
     */
    public function unfreeze(): bool
    {
        return $this->update(['wallet_status' => 'ACTIVE']);
    }

    /**
     * Close the wallet (permanent - for account closure)
     */
    public function close(): bool
    {
        // Ensure no locked funds before closing
        if ($this->locked_escrow_funds > 0) {
            throw new \Exception('Cannot close wallet with locked escrow funds');
        }

        return $this->update(['wallet_status' => 'CLOSED']);
    }

    /**
     * Reconcile wallet balance with ledger entries
     * This should be run daily as a verification job
     */
    public function reconcileWithLedger(): array
    {
        $calculatedBalance = DB::table('ledger_entries')
            ->where('wallet_id', $this->id)
            ->whereNotIn('reference_table', ['escrow_vault']) // Exclude escrow entries
            ->selectRaw('
                SUM(CASE WHEN type = \'CREDIT\' THEN amount ELSE 0 END) -
                SUM(CASE WHEN type = \'DEBIT\' THEN amount ELSE 0 END) as balance
            ')
            ->value('balance') ?? 0;

        $discrepancy = abs($this->available_balance - $calculatedBalance);

        return [
            'wallet_balance' => $this->available_balance,
            'ledger_balance' => $calculatedBalance,
            'discrepancy' => $discrepancy,
            'is_balanced' => $discrepancy < 0.01, // Allow 1 cent tolerance for rounding
        ];
    }

    /**
     * Get transaction history for this wallet
     */
    public function getTransactionHistory(int $limit = 50)
    {
        return $this->ledgerEntries()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'date' => $entry->created_at,
                    'type' => $entry->type,
                    'amount' => $entry->amount,
                    'description' => $entry->description,
                    'reference' => $entry->reference_table . '#' . $entry->reference_id,
                    'balance_after' => $this->calculateBalanceAtTime($entry->created_at),
                ];
            });
    }

    /**
     * Calculate balance at a specific point in time
     * Useful for historical reporting
     */
    private function calculateBalanceAtTime($timestamp): float
    {
        return DB::table('ledger_entries')
            ->where('wallet_id', $this->id)
            ->where('created_at', '<=', $timestamp)
            ->whereNotIn('reference_table', ['escrow_vault'])
            ->selectRaw('
                SUM(CASE WHEN type = \'CREDIT\' THEN amount ELSE 0 END) -
                SUM(CASE WHEN type = \'DEBIT\' THEN amount ELSE 0 END) as balance
            ')
            ->value('balance') ?? 0;
    }

    /**
     * Get locked funds breakdown
     */
    public function getLockedFundsBreakdown(): array
    {
        $escrows = DB::table('escrow_vault')
            ->where(function($query) {
                $query->where('buyer_wallet_id', $this->id)
                      ->orWhere('seller_wallet_id', $this->id);
            })
            ->where('status', 'LOCKED')
            ->get();

        return [
            'total_locked' => $this->locked_escrow_funds,
            'count' => $escrows->count(),
            'escrows' => $escrows->map(function($escrow) {
                return [
                    'order_id' => $escrow->order_id,
                    'amount' => $escrow->amount,
                    'auto_release_at' => $escrow->auto_release_timestamp,
                ];
            }),
        ];
    }

    // ============================================
    // EVENTS
    // ============================================

    /**
     * Boot the model and register events
     */
    protected static function boot()
    {
        parent::boot();

        // Prevent deletion of wallets with funds
        static::deleting(function ($wallet) {
            if ($wallet->total_balance > 0) {
                throw new \Exception('Cannot delete wallet with remaining balance');
            }
        });
    }
}