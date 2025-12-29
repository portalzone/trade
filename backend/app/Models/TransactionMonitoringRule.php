<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionMonitoringRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'rule_name',
        'rule_type',
        'severity',
        'conditions',
        'description',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function alerts(): HasMany
    {
        return $this->hasMany(SuspiciousActivityAlert::class, 'rule_id');
    }

    /**
     * Check if rule is active
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Scope for active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->orderBy('priority', 'asc');
    }

    /**
     * Scope by rule type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('rule_type', $type);
    }
}
