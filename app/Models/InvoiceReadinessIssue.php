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
    'issue_key', 'invoice_readiness_sample_id', 'invoice_sample_field_id', 'data_quality_rule_version_id',
    'severity_snapshot', 'behavior_snapshot', 'explanation_snapshot', 'remediation_snapshot',
    'evidence_note', 'recorded_by', 'recorded_at',
])]
final class InvoiceReadinessIssue extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Invoice readiness issues are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Invoice readiness issues are immutable.'));
    }

    /** @return BelongsTo<InvoiceReadinessSample, $this> */
    public function sample(): BelongsTo
    {
        return $this->belongsTo(InvoiceReadinessSample::class, 'invoice_readiness_sample_id');
    }

    /** @return BelongsTo<InvoiceSampleField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(InvoiceSampleField::class, 'invoice_sample_field_id');
    }

    /** @return BelongsTo<DataQualityRuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(DataQualityRuleVersion::class, 'data_quality_rule_version_id');
    }

    /** @return HasOne<InvoiceIssueResolution, $this> */
    public function resolution(): HasOne
    {
        return $this->hasOne(InvoiceIssueResolution::class);
    }

    protected function casts(): array
    {
        return ['severity_snapshot' => DataQualitySeverity::class, 'behavior_snapshot' => DataQualityBehavior::class, 'recorded_at' => 'datetime'];
    }
}
