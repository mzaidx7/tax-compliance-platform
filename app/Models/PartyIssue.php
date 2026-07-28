<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DataQualityBehavior;
use App\Enums\DataQualitySeverity;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable([
    'issue_key', 'party_record_id', 'party_field_version_id', 'data_quality_rule_version_id',
    'severity_snapshot', 'behavior_snapshot', 'explanation_snapshot', 'remediation_snapshot',
    'evidence_note', 'recorded_by', 'recorded_at',
])]
final class PartyIssue extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Party issues are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Party issues are immutable.'));
    }

    /** @return BelongsTo<PartyRecord, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(PartyRecord::class, 'party_record_id');
    }

    /** @return BelongsTo<PartyFieldVersion, $this> */
    public function fieldVersion(): BelongsTo
    {
        return $this->belongsTo(PartyFieldVersion::class, 'party_field_version_id');
    }

    /** @return BelongsTo<DataQualityRuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(DataQualityRuleVersion::class, 'data_quality_rule_version_id');
    }

    /** @return HasOne<PartyIssueResolution, $this> */
    public function resolution(): HasOne
    {
        return $this->hasOne(PartyIssueResolution::class);
    }

    protected function casts(): array
    {
        return ['severity_snapshot' => DataQualitySeverity::class, 'behavior_snapshot' => DataQualityBehavior::class, 'recorded_at' => 'datetime'];
    }
}
