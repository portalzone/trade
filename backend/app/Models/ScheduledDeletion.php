<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledDeletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'record_type',
        'record_id',
        'scheduled_for',
        'status',
        'policy_id',
        'deleted_at',
        'deletion_notes',
    ];

    protected $casts = [
        'scheduled_for' => 'date',
        'deleted_at' => 'datetime',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(RecordRetentionPolicy::class, 'policy_id');
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(?string $notes = null): void
    {
        $this->update([
            'status' => 'completed',
            'deleted_at' => now(),
            'deletion_notes' => $notes,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $notes): void
    {
        $this->update([
            'status' => 'failed',
            'deletion_notes' => $notes,
        ]);
    }
}
