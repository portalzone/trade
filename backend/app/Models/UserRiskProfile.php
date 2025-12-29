<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRiskProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'overall_risk_score',
        'risk_level',
        'velocity_score',
        'pattern_score',
        'compliance_score',
        'risk_factors',
        'total_alerts',
        'resolved_alerts',
        'last_alert_at',
        'last_review_at',
    ];

    protected $casts = [
        'overall_risk_score' => 'decimal:2',
        'velocity_score' => 'integer',
        'pattern_score' => 'integer',
        'compliance_score' => 'integer',
        'risk_factors' => 'array',
        'total_alerts' => 'integer',
        'resolved_alerts' => 'integer',
        'last_alert_at' => 'datetime',
        'last_review_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Update risk score
     */
    public function updateRiskScore(): void
    {
        // Calculate overall risk score from components
        $score = (
            ($this->velocity_score * 0.4) +
            ($this->pattern_score * 0.3) +
            ((100 - $this->compliance_score) * 0.3)
        );

        // Determine risk level
        $level = 'low';
        if ($score >= 70) {
            $level = 'critical';
        } elseif ($score >= 50) {
            $level = 'high';
        } elseif ($score >= 30) {
            $level = 'medium';
        }

        $this->update([
            'overall_risk_score' => round($score, 2),
            'risk_level' => $level,
        ]);
    }

    /**
     * Increment alert count
     */
    public function incrementAlerts(): void
    {
        $this->increment('total_alerts');
        $this->update(['last_alert_at' => now()]);
    }

    /**
     * Mark alert as resolved
     */
    public function markAlertResolved(): void
    {
        $this->increment('resolved_alerts');
    }
}
