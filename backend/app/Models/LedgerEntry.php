<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'wallet_id',
        'type',
        'amount',
        'description',
        'reference_table',
        'reference_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the wallet that owns this ledger entry
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Scope: Credit entries
     */
    public function scopeCredits($query)
    {
        return $query->where('type', 'CREDIT');
    }

    /**
     * Scope: Debit entries
     */
    public function scopeDebits($query)
    {
        return $query->where('type', 'DEBIT');
    }

    /**
     * Scope: By wallet
     */
    public function scopeByWallet($query, $walletId)
    {
        return $query->where('wallet_id', $walletId);
    }
}
