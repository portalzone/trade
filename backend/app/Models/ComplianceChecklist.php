<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceChecklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_type',
        'review_date',
        'checklist_items',
        'items_completed',
        'items_total',
        'completion_percentage',
        'status',
        'assigned_to',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'review_date' => 'date',
        'checklist_items' => 'array',
        'items_completed' => 'integer',
        'items_total' => 'integer',
        'completion_percentage' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Update completion percentage
     */
    public function updateProgress(): void
    {
        $completed = collect($this->checklist_items)
            ->filter(fn($item) => $item['completed'] ?? false)
            ->count();

        $percentage = $this->items_total > 0
            ? ($completed / $this->items_total) * 100
            : 0;

        $this->update([
            'items_completed' => $completed,
            'completion_percentage' => round($percentage, 2),
            'status' => $completed === $this->items_total ? 'completed' : 'in_progress',
            'completed_at' => $completed === $this->items_total ? now() : null,
        ]);
    }

    /**
     * Mark item as complete
     */
    public function completeItem(int $itemIndex): void
    {
        $items = $this->checklist_items;
        if (isset($items[$itemIndex])) {
            $items[$itemIndex]['completed'] = true;
            $items[$itemIndex]['completed_at'] = now()->toDateTimeString();
            $this->checklist_items = $items;
            $this->save();
            $this->updateProgress();
        }
    }
}
