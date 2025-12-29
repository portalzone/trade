<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegulatorySubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_type',
        'regulator',
        'reference_number',
        'submission_data',
        'file_path',
        'status',
        'submitted_at',
        'acknowledged_at',
        'submitted_by',
        'response_data',
    ];

    protected $casts = [
        'submission_data' => 'array',
        'response_data' => 'array',
        'submitted_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($submission) {
            if (empty($submission->reference_number)) {
                $prefix = strtoupper(substr($submission->regulator, 0, 3));
                $submission->reference_number = $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            }
            if (empty($submission->submitted_at)) {
                $submission->submitted_at = now();
            }
        });
    }

    /**
     * Mark as acknowledged
     */
    public function markAsAcknowledged(array $response = null): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'response_data' => $response,
        ]);
    }
}
