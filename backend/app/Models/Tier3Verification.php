<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tier3Verification extends Model
{
    protected $fillable = [
        'user_id',
        'annual_revenue',
        'transaction_volume',
        'source_of_funds',
        'business_purpose',
        'financial_statements_path',
        'financial_statements_url',
        'bank_statements_path',
        'bank_statements_url',
        'verification_status',
        'rejection_reason',
        'submitted_at',
        'verified_at',
        'rejected_at',
        'verified_by',
        'rejected_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function beneficialOwners(): HasMany
    {
        return $this->hasMany(BeneficialOwner::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
