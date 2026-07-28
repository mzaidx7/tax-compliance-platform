<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['checklist_version_id', 'item_key', 'label', 'position', 'required'])]
final class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Published checklist items are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Published checklist items are immutable.'));
    }

    /** @return BelongsTo<ChecklistVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ChecklistVersion::class, 'checklist_version_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['position' => 'integer', 'required' => 'boolean'];
    }
}
