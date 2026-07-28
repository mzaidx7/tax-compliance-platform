<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssignmentRole;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\AssignmentHistoryFactory;
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
 * @property AssignmentRole $assignment_role
 * @property string $assigned_membership_id
 * @property int $assigned_by
 * @property string $reason
 * @property Carbon $assigned_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'work_item_id',
    'assignment_role',
    'assigned_membership_id',
    'assigned_by',
    'reason',
    'assigned_at',
])]
final class AssignmentHistory extends Model
{
    /** @use HasFactory<AssignmentHistoryFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Assignment history is append-only.');
        });

        self::deleting(function (): never {
            throw new LogicException('Assignment history is append-only.');
        });
    }

    /**
     * @return BelongsTo<WorkItem, $this>
     */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    /**
     * @return BelongsTo<FirmMembership, $this>
     */
    public function assignedMembership(): BelongsTo
    {
        return $this->belongsTo(FirmMembership::class, 'assigned_membership_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assignment_role' => AssignmentRole::class,
            'assigned_at' => 'datetime',
        ];
    }
}
