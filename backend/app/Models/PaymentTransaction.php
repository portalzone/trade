<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PaymentTransaction extends Model
{
    use HasUuids;

    protected $table = 'payment_transactions';

    protected $fillable = [
        'wallet_id',
        'transaction_type',
        'payment_method',
        'amount',
        'status',
        'gateway_reference',
        'gateway_data',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_data' => 'array',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
