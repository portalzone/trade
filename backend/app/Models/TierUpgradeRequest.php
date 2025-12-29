<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TierUpgradeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'current_tier',
        'requested_tier',
        'status',
        'justification',
        'reviewed_by',
        'review_notes',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'current_tier' => 'integer',
        'requested_tier' => 'integer',
        'submitted_at' => 'datetime',
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
     * Approve request
     */
    public function approve(int $reviewedBy, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewedBy,
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Reject request
     */
    public function reject(int $reviewedBy, string $notes): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewedBy,
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);
    }
}
