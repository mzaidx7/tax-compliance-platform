<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DataQualityBehavior;
use App\Enums\DataQualitySeverity;
use App\Enums\RuleVersionStatus;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $data_quality_rule_definition_id
 * @property int $version
 * @property RuleVersionStatus $status
 * @property DataQualitySeverity $severity
 * @property DataQualityBehavior $behavior
 * @property int $prepared_by
 * @property int|null $verified_by
 */
#[Fillable([
    'data_quality_rule_definition_id', 'version', 'status', 'applicability_criteria', 'severity', 'behavior',
    'explanation', 'remediation_guidance', 'source_kind', 'source_title', 'source_url',
    'formula_version_effect', 'prepared_by', 'verified_by', 'source_last_verified_on',
    'verified_at', 'approved_at', 'published_at', 'change_summary',
])]
final class DataQualityRuleVersion extends Model
{
    use BelongsToFirm, HasUlids;

    /** @var list<string> */
    private const CONTENT_FIELDS = [
        'applicability_criteria', 'severity', 'behavior', 'explanation', 'remediation_guidance',
        'source_kind', 'source_title', 'source_url', 'formula_version_effect', 'change_summary',
    ];

    protected static function booted(): void
    {
        self::updating(function (self $version): void {
            $from = RuleVersionStatus::from((string) $version->getRawOriginal('status'));
            if ($from !== RuleVersionStatus::Draft && $version->isDirty(self::CONTENT_FIELDS)) {
                throw new LogicException('Readiness rule content is immutable after draft.');
            }
        });
        self::deleting(fn (): never => throw new LogicException('Readiness rule versions cannot be deleted.'));
    }

    /** @return BelongsTo<DataQualityRuleDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(DataQualityRuleDefinition::class, 'data_quality_rule_definition_id');
    }

    /** @return HasMany<DataQualityRuleEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(DataQualityRuleEvent::class);
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer', 'status' => RuleVersionStatus::class, 'severity' => DataQualitySeverity::class,
            'behavior' => DataQualityBehavior::class, 'source_last_verified_on' => 'date',
            'verified_at' => 'datetime', 'approved_at' => 'datetime', 'published_at' => 'datetime',
        ];
    }
}
