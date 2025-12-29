<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationQueue extends Model
{
    use HasFactory;

    protected $table = 'notification_queue';

    protected $fillable = [
        'user_id',
        'notification_type',
        'channel',
        'priority',
        'status',
        'data',
        'retry_count',
        'scheduled_for',
        'sent_at',
        'failure_reason',
    ];

    protected $casts = [
        'data' => 'array',
        'retry_count' => 'integer',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
        
        $this->increment('retry_count');
    }

    /**
     * Check if should retry
     */
    public function shouldRetry(): bool
    {
        return $this->retry_count < 3 && $this->status === 'failed';
    }

    /**
     * Scope for pending notifications
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('scheduled_for')
                  ->orWhere('scheduled_for', '<=', now());
            });
    }
}
