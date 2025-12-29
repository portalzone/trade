<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TierChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'from_tier',
        'to_tier',
        'change_type',
        'reason',
        'notes',
        'triggered_by',
        'notification_sent',
        'notified_at',
        'metadata',
    ];

    protected $casts = [
        'from_tier' => 'integer',
        'to_tier' => 'integer',
        'notification_sent' => 'boolean',
        'notified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * Mark notification as sent
     */
    public function markNotificationSent(): void
    {
        $this->update([
            'notification_sent' => true,
            'notified_at' => now(),
        ]);
    }

    /**
     * Check if this is an upgrade
     */
    public function isUpgrade(): bool
    {
        return $this->to_tier > $this->from_tier;
    }

    /**
     * Check if this is a downgrade
     */
    public function isDowngrade(): bool
    {
        return $this->to_tier < $this->from_tier;
    }
}
