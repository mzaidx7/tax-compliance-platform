<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RuleVersionStatus;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['data_quality_rule_version_id', 'from_status', 'to_status', 'acted_by', 'reason', 'occurred_at'])]
final class DataQualityRuleEvent extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Readiness rule history is append-only.'));
        self::deleting(fn (): never => throw new LogicException('Readiness rule history is append-only.'));
    }

    /** @return BelongsTo<DataQualityRuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(DataQualityRuleVersion::class, 'data_quality_rule_version_id');
    }

    protected function casts(): array
    {
        return ['from_status' => RuleVersionStatus::class, 'to_status' => RuleVersionStatus::class, 'occurred_at' => 'datetime'];
    }
}
