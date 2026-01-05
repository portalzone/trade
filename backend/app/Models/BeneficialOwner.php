<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BeneficialOwner extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_verification_id',
        'tier3_verification_id',
        'full_name',
        'nin',
        'bvn',
        'passport_number',
        'date_of_birth',
        'nationality',
        'phone',
        'email',
        'residential_address',
        'ownership_percentage',
        'ownership_type',
        'is_pep',
        'pep_details',
        'id_type',
        'id_number',
        'id_document_type',
        'id_document_path',
        'id_document_url',
        'proof_of_address_path',
        'proof_of_address_url',
        'is_verified',
        'sanctions_cleared',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'ownership_percentage' => 'integer',
        'is_pep' => 'boolean',
        'is_verified' => 'boolean',
        'sanctions_cleared' => 'boolean',
    ];

    public function businessVerification(): BelongsTo
    {
        return $this->belongsTo(BusinessVerification::class);
    }

    public function tier3Verification(): BelongsTo
    {
        return $this->belongsTo(Tier3Verification::class);
    }

    public function sanctionsScreenings(): HasMany
    {
        return $this->hasMany(SanctionsScreeningResult::class);
    }

    /**
     * Check if UBO meets threshold (>25% ownership)
     */
    public function meetsUboThreshold(): bool
    {
        return $this->ownership_percentage > 25;
    }

    /**
     * Check if politically exposed person
     */
    public function isPoliticallyExposed(): bool
    {
        return $this->is_pep === true;
    }
}