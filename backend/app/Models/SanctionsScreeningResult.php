<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionsScreeningResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_verification_id',
        'beneficial_owner_id',
        'screened_name',
        'screening_type',
        'status',
        'screening_lists',
        'matches',
        'match_score',
        'notes',
        'reviewed_by',
        'screened_at',
        'reviewed_at',
    ];

    protected $casts = [
        'screening_lists' => 'array',
        'matches' => 'array',
        'match_score' => 'integer',
        'screened_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function businessVerification(): BelongsTo
    {
        return $this->belongsTo(BusinessVerification::class);
    }

    public function beneficialOwner(): BelongsTo
    {
        return $this->belongsTo(BeneficialOwner::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Check if screening is clear
     */
    public function isClear(): bool
    {
        return $this->status === 'clear';
    }

    /**
     * Check if has potential match
     */
    public function hasPotentialMatch(): bool
    {
        return in_array($this->status, ['potential_match', 'confirmed_match']);
    }

    /**
     * Check if needs review
     */
    public function needsReview(): bool
    {
        return $this->status === 'potential_match' && is_null($this->reviewed_at);
    }
}
