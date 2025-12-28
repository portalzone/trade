<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusinessVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tier',
        'business_name',
        'registration_number',
        'cac_number',
        'registration_date',
        'business_address',
        'business_phone',
        'business_email',
        'business_type',
        'cac_certificate_path',
        'cac_certificate_url',
        'tin_certificate_path',
        'tin_certificate_url',
        'additional_documents',
        'total_ownership_declared',
        'all_ubos_identified',
        'sanctions_screening_completed',
        'edd_completed',
        'edd_started_at',
        'edd_completed_at',
        'verification_status',
        'rejection_reason',
        'verification_notes',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
        'verified_at',
    ];

    protected $casts = [
        'additional_documents' => 'array',
        'verification_notes' => 'array',
        'registration_date' => 'date',
        'all_ubos_identified' => 'boolean',
        'sanctions_screening_completed' => 'boolean',
        'edd_completed' => 'boolean',
        'edd_started_at' => 'datetime',
        'edd_completed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function directors(): HasMany
    {
        return $this->hasMany(BusinessDirector::class);
    }

    public function beneficialOwners(): HasMany
    {
        return $this->hasMany(BeneficialOwner::class);
    }

    public function sanctionsScreenings(): HasMany
    {
        return $this->hasMany(SanctionsScreeningResult::class);
    }

    public function eddReview(): HasOne
    {
        return $this->hasOne(EddReview::class);
    }

    /**
     * Check if verification is pending
     */
    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    /**
     * Check if verification is verified
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /**
     * Check if verification is rejected
     */
    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    /**
     * Check if Tier 3 verification
     */
    public function isTier3(): bool
    {
        return $this->tier === 'tier3';
    }

    /**
     * Check if ready for Tier 3 verification
     */
    public function isReadyForTier3Verification(): bool
    {
        if (!$this->isTier3()) {
            return false;
        }

        return $this->all_ubos_identified
            && $this->sanctions_screening_completed
            && $this->edd_completed
            && $this->total_ownership_declared >= 100;
    }

    /**
     * Mark as submitted
     */
    public function markAsSubmitted(): void
    {
        $this->update([
            'verification_status' => 'under_review',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Mark as verified
     */
    public function markAsVerified(User $admin): void
    {
        $this->update([
            'verification_status' => 'verified',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'verified_at' => now(),
        ]);

        // Upgrade user tier
        $tier = $this->tier === 'tier3' ? 3 : 2;
        $this->user->update([
            'kyc_tier' => $tier,
        ]);
    }

    /**
     * Mark as rejected
     */
    public function markAsRejected(User $admin, string $reason): void
    {
        $this->update([
            'verification_status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }
}
