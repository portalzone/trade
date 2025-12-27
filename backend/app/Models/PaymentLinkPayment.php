<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLinkPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_link_id',
        'payer_id',
        'payer_name',
        'payer_email',
        'payer_phone',
        'amount',
        'reference',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /**
     * Generate unique reference
     */
    public static function generateReference(): string
    {
        return 'PL-' . strtoupper(uniqid()) . '-' . time();
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
