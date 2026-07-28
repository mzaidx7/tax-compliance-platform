<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkItemStatus;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\WorkItemTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $work_item_id
 * @property WorkItemStatus $from_status
 * @property WorkItemStatus $to_status
 * @property int $transitioned_by
 * @property string $reason
 * @property Carbon $transitioned_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'work_item_id',
    'from_status',
    'to_status',
    'transitioned_by',
    'reason',
    'transitioned_at',
])]
final class WorkItemTransition extends Model
{
    /** @use HasFactory<WorkItemTransitionFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Work item transition history is append-only.');
        });

        self::deleting(function (): never {
            throw new LogicException('Work item transition history is append-only.');
        });
    }

    /** @return BelongsTo<WorkItem, $this> */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transitioned_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => WorkItemStatus::class,
            'to_status' => WorkItemStatus::class,
            'transitioned_at' => 'datetime',
        ];
    }
}
