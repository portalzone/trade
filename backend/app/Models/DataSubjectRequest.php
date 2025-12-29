<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSubjectRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number',
        'user_id',
        'request_type',
        'status',
        'request_details',
        'rejection_reason',
        'assigned_to',
        'data_export_path',
        'completed_at',
        'data_deleted_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'completed_at' => 'datetime',
        'data_deleted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($request) {
            if (empty($request->request_number)) {
                $request->request_number = 'DSR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Mark request as processing
     */
    public function markAsProcessing(?int $assignedTo = null): void
    {
        $this->update([
            'status' => 'processing',
            'assigned_to' => $assignedTo,
        ]);
    }

    /**
     * Mark request as completed
     */
    public function markAsCompleted(?string $exportPath = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'data_export_path' => $exportPath,
        ]);
    }

    /**
     * Mark request as rejected
     */
    public function markAsRejected(string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'completed_at' => now(),
        ]);
    }
}
