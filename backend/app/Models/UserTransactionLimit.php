<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTransactionLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tier',
        'per_transaction_limit',
        'daily_limit',
        'monthly_limit',
    ];

    protected $casts = [
        'per_transaction_limit' => 'decimal:2',
        'daily_limit' => 'decimal:2',
        'monthly_limit' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
