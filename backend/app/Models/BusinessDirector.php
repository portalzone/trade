<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDirector extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_verification_id',
        'full_name',
        'nin',
        'bvn',
        'date_of_birth',
        'phone',
        'email',
        'residential_address',
        'ownership_percentage',
        'is_primary_contact',
        'role',
        'id_document_path',
        'id_document_url',
        'is_verified',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'ownership_percentage' => 'integer',
        'is_primary_contact' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function businessVerification(): BelongsTo
    {
        return $this->belongsTo(BusinessVerification::class);
    }
}
