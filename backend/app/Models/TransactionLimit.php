<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'tier',
        'per_transaction_limit',
        'daily_limit',
        'monthly_limit',
    ];

    protected $casts = [
        'tier' => 'integer',
        'per_transaction_limit' => 'decimal:2',
        'daily_limit' => 'decimal:2',
        'monthly_limit' => 'decimal:2',
    ];

    /**
     * Get limits for a specific tier
     */
    public static function getForTier(int $tier): ?self
    {
        return self::where('tier', $tier)->first();
    }
}
