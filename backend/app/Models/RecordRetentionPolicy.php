<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecordRetentionPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'data_type',
        'retention_years',
        'description',
        'deletion_method',
        'is_active',
    ];

    protected $casts = [
        'retention_years' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scheduledDeletions(): HasMany
    {
        return $this->hasMany(ScheduledDeletion::class, 'policy_id');
    }

    /**
     * Calculate deletion date for a record
     */
    public function getDeletionDate(\DateTime $recordDate): \DateTime
    {
        $deletionDate = clone $recordDate;
        $deletionDate->modify("+{$this->retention_years} years");
        return $deletionDate;
    }
}
