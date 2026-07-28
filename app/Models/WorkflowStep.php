<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssignmentRole;
use App\Enums\WorkItemStatus;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property WorkItemStatus $from_status
 * @property WorkItemStatus $to_status
 * @property AssignmentRole $assignment_role
 */
#[Fillable(['workflow_definition_id', 'from_status', 'to_status', 'assignment_role', 'position'])]
final class WorkflowStep extends Model
{
    /** @use HasFactory<WorkflowStepFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Published workflow steps are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Published workflow steps are immutable.'));
    }

    /** @return BelongsTo<WorkflowDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => WorkItemStatus::class,
            'to_status' => WorkItemStatus::class,
            'assignment_role' => AssignmentRole::class,
            'position' => 'integer',
        ];
    }
}
