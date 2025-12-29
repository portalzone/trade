<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuspiciousActivityReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'sar_number',
        'user_id',
        'alert_id',
        'summary',
        'transactions',
        'alerts',
        'total_amount',
        'filing_status',
        'filed_at',
        'filed_by',
        'regulatory_response',
    ];

    protected $casts = [
        'transactions' => 'array',
        'alerts' => 'array',
        'total_amount' => 'decimal:2',
        'filed_at' => 'datetime',
        'regulatory_response' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sar) {
            if (empty($sar->sar_number)) {
                $sar->sar_number = 'SAR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(SuspiciousActivityAlert::class, 'alert_id');
    }

    /**
     * Mark SAR as submitted
     */
    public function markAsSubmitted(string $filedBy): void
    {
        $this->update([
            'filing_status' => 'submitted',
            'filed_at' => now(),
            'filed_by' => $filedBy,
        ]);
    }
}
