<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertFeedback extends Model
{
    use HasFactory;

    protected $table = 'alert_feedback';

    protected $fillable = [
        'alert_id',
        'reviewed_by',
        'is_true_positive',
        'feedback_notes',
        'improvement_suggestions',
    ];

    protected $casts = [
        'is_true_positive' => 'boolean',
        'improvement_suggestions' => 'array',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(SuspiciousActivityAlert::class, 'alert_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
