<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type',
        'report_period',
        'period_start',
        'period_end',
        'status',
        'report_data',
        'statistics',
        'file_path',
        'generated_by',
        'submitted_at',
        'submitted_to',
        'submission_response',
        'acknowledged_at',
    ];

    protected $casts = [
        'report_data' => 'array',
        'statistics' => 'array',
        'submission_response' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'submitted_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Mark report as submitted
     */
    public function markAsSubmitted(string $submittedTo, ?int $userId = null): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'submitted_to' => $submittedTo,
            'generated_by' => $userId,
        ]);
    }

    /**
     * Mark report as acknowledged
     */
    public function markAsAcknowledged(array $response = null): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'submission_response' => $response,
        ]);
    }
}
