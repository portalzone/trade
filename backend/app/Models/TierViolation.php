<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TierViolation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'violation_type',
        'severity',
        'description',
        'evidence',
        'action_taken',
        'tier_affected',
        'previous_tier',
        'new_tier',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'tier_affected' => 'boolean',
        'previous_tier' => 'integer',
        'new_tier' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Mark as reviewed
     */
    public function markAsReviewed(int $reviewedBy, string $actionTaken): void
    {
        $this->update([
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'action_taken' => $actionTaken,
        ]);
    }
}
