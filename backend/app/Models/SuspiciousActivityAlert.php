<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SuspiciousActivityAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'order_id',
        'rule_id',
        'alert_type',
        'severity',
        'status',
        'alert_data',
        'risk_score',
        'notes',
        'assigned_to',
        'investigated_at',
        'resolved_at',
    ];

    protected $casts = [
        'alert_data' => 'array',
        'risk_score' => 'decimal:2',
        'investigated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(TransactionMonitoringRule::class, 'rule_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(AlertFeedback::class, 'alert_id');
    }

    public function sar(): HasOne
    {
        return $this->hasOne(SuspiciousActivityReport::class, 'alert_id');
    }

    /**
     * Mark alert as investigating
     */
    public function markAsInvestigating(?int $assignedTo = null): void
    {
        $this->update([
            'status' => 'investigating',
            'assigned_to' => $assignedTo,
            'investigated_at' => now(),
        ]);
    }

    /**
     * Mark alert as resolved
     */
    public function markAsResolved(string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Mark alert as false positive
     */
    public function markAsFalsePositive(string $notes = null): void
    {
        $this->update([
            'status' => 'false_positive',
            'resolved_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Scope for new alerts
     */
    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    /**
     * Scope by severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope for critical alerts
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope for unresolved alerts
     */
    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['new', 'investigating']);
    }
}
