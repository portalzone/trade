<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waybill extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'waybill_number',
        'tracking_code',
        'sender_name',
        'sender_address',
        'sender_phone',
        'recipient_name',
        'recipient_address',
        'recipient_phone',
        'item_description',
        'weight',
        'dimensions',
        'declared_value',
        'delivery_type',
        'courier_service',
        'metadata',
        'generated_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'weight' => 'decimal:2',
        'declared_value' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Generate unique waybill number
     */
    public static function generateWaybillNumber(): string
    {
        do {
            $number = 'WB-' . strtoupper(uniqid()) . '-' . time();
        } while (self::where('waybill_number', $number)->exists());

        return $number;
    }

    /**
     * Generate tracking code
     */
    public static function generateTrackingCode(): string
    {
        do {
            $code = 'TRK-' . strtoupper(substr(md5(uniqid()), 0, 10));
        } while (self::where('tracking_code', $code)->exists());

        return $code;
    }
}
