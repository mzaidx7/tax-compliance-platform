<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Database\Factories\WorkflowDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $definition_key
 * @property string $name
 * @property int $version
 * @property string $status
 */
#[Fillable(['definition_key', 'name', 'version', 'status', 'published_by', 'published_at'])]
final class WorkflowDefinition extends Model
{
    public const CORE_KEY = 'core-compliance-workflow';

    /** @use HasFactory<WorkflowDefinitionFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Published workflow definitions are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Published workflow definitions are immutable.'));
    }

    /** @return HasMany<WorkflowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('position');
    }

    /** @return HasMany<WorkItem, $this> */
    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['version' => 'integer', 'published_at' => 'datetime'];
    }
}
