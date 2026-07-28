<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Database\Factories\WorkItemChecklistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['work_item_id', 'checklist_version_id', 'applied_by', 'applied_at'])]
final class WorkItemChecklist extends Model
{
    /** @use HasFactory<WorkItemChecklistFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    /** @return BelongsTo<WorkItem, $this> */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    /** @return BelongsTo<ChecklistVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ChecklistVersion::class, 'checklist_version_id');
    }

    /** @return HasMany<ChecklistItemCompletion, $this> */
    public function completions(): HasMany
    {
        return $this->hasMany(ChecklistItemCompletion::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['applied_at' => 'datetime'];
    }
}
