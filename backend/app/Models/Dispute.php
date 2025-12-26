<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'raised_by_user_id',
        'dispute_reason',
        'dispute_status',
        'admin_notes',
        'resolution_details',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the order this dispute belongs to
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who raised this dispute
     */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user_id');
    }

    /**
     * Check if dispute is open
     */
    public function isOpen(): bool
    {
        return $this->dispute_status === 'OPEN';
    }

    /**
     * Check if dispute is under review
     */
    public function isUnderReview(): bool
    {
        return $this->dispute_status === 'UNDER_REVIEW';
    }

    /**
     * Check if dispute is resolved
     */
    public function isResolved(): bool
    {
        return in_array($this->dispute_status, [
            'RESOLVED_BUYER',
            'RESOLVED_SELLER',
            'RESOLVED_REFUND'
        ]);
    }

    /**
     * Scope: Open disputes
     */
    public function scopeOpen($query)
    {
        return $query->where('dispute_status', 'OPEN');
    }

    /**
     * Scope: Under review disputes
     */
    public function scopeUnderReview($query)
    {
        return $query->where('dispute_status', 'UNDER_REVIEW');
    }
}
