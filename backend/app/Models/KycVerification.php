<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'verification_type',
        'identifier',
        'status',
        'provider',
        'provider_response',
        'verified_data',
        'verification_attempts',
        'last_attempt_at',
        'verified_at',
        'admin_reviewed_by',
        'admin_notes',
    ];

    protected $casts = [
        'provider_response' => 'array',
        'verified_data' => 'array',
        'last_attempt_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adminReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_reviewed_by');
    }

    /**
     * Check if verification is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if verification is verified
     */
    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * Check if verification needs manual review
     */
    public function needsManualReview(): bool
    {
        return $this->status === 'manual_review';
    }

    /**
     * Mark as verified
     */
    public function markAsVerified(array $verifiedData): void
    {
        $this->update([
            'status' => 'verified',
            'verified_data' => $verifiedData,
            'verified_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'last_attempt_at' => now(),
        ]);
        
        $this->increment('verification_attempts');
    }

    /**
     * Send for manual review
     */
    public function sendForManualReview(): void
    {
        $this->update([
            'status' => 'manual_review',
        ]);
    }
}
