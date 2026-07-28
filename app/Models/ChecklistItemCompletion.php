<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Database\Factories\ChecklistItemCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'work_item_checklist_id',
    'checklist_item_id',
    'completed_by',
    'evidence_note',
    'completed_at',
])]
final class ChecklistItemCompletion extends Model
{
    /** @use HasFactory<ChecklistItemCompletionFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Checklist completion evidence is append-only.'));
        self::deleting(fn (): never => throw new LogicException('Checklist completion evidence is append-only.'));
    }

    /** @return BelongsTo<WorkItemChecklist, $this> */
    public function workItemChecklist(): BelongsTo
    {
        return $this->belongsTo(WorkItemChecklist::class);
    }

    /** @return BelongsTo<ChecklistItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
