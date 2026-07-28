<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RiskLevel;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\WorkItemRiskChangeFactory;
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
 * @property RiskLevel|null $previous_risk_status
 * @property RiskLevel $new_risk_status
 * @property int $changed_by
 * @property string $reason
 * @property Carbon $changed_at
 */
#[Fillable([
    'work_item_id',
    'previous_risk_status',
    'new_risk_status',
    'changed_by',
    'reason',
    'changed_at',
])]
final class WorkItemRiskChange extends Model
{
    /** @use HasFactory<WorkItemRiskChangeFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Work item risk history is append-only.');
        });

        self::deleting(function (): never {
            throw new LogicException('Work item risk history is append-only.');
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
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'previous_risk_status' => RiskLevel::class,
            'new_risk_status' => RiskLevel::class,
            'changed_at' => 'datetime',
        ];
    }
}
