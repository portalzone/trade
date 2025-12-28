<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EddReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_verification_id',
        'status',
        'required_documents',
        'submitted_documents',
        'source_of_funds',
        'source_of_wealth',
        'business_model_description',
        'expected_transaction_volume',
        'geographic_exposure',
        'premises_inspection_required',
        'premises_inspection_date',
        'premises_inspection_notes',
        'reference_checks_completed',
        'reference_contacts',
        'assigned_to',
        'reviewed_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'required_documents' => 'array',
        'submitted_documents' => 'array',
        'reference_contacts' => 'array',
        'premises_inspection_required' => 'boolean',
        'reference_checks_completed' => 'boolean',
        'premises_inspection_date' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function businessVerification(): BelongsTo
    {
        return $this->belongsTo(BusinessVerification::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Check if EDD is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed' && !is_null($this->completed_at);
    }

    /**
     * Check if in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Mark as started
     */
    public function markAsStarted(User $assignee): void
    {
        $this->update([
            'status' => 'in_progress',
            'assigned_to' => $assignee->id,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(User $reviewer): void
    {
        $this->update([
            'status' => 'completed',
            'reviewed_by' => $reviewer->id,
            'completed_at' => now(),
        ]);
    }
}
