<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenerationRunStatus;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property GenerationRunStatus $status
 * @property string $deterministic_key
 * @property string|null $preview_run_id
 * @property string $client_id
 * @property string $client_service_enrollment_id
 * @property string|null $tax_period_id
 * @property string $obligation_rule_version_id
 * @property array<string, mixed> $input_snapshot
 * @property array<string, mixed> $parameter_snapshot
 * @property array<string, mixed> $result_snapshot
 * @property Carbon $statutory_due_date
 * @property Carbon|null $internal_target_date
 * @property string $calculation_explanation
 * @property int $created_by
 * @property-read Client $client
 * @property-read ClientServiceEnrollment $serviceEnrollment
 * @property-read TaxPeriod|null $taxPeriod
 * @property-read ObligationRuleVersion $ruleVersion
 */
#[Fillable([
    'status',
    'deterministic_key',
    'preview_run_id',
    'client_id',
    'client_service_enrollment_id',
    'tax_period_id',
    'obligation_rule_version_id',
    'input_snapshot',
    'parameter_snapshot',
    'result_snapshot',
    'statutory_due_date',
    'internal_target_date',
    'calculation_explanation',
    'created_by',
])]
final class ObligationGenerationRun extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Obligation generation runs are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Obligation generation runs are immutable.'));
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<ClientServiceEnrollment, $this> */
    public function serviceEnrollment(): BelongsTo
    {
        return $this->belongsTo(ClientServiceEnrollment::class, 'client_service_enrollment_id');
    }

    /** @return BelongsTo<TaxPeriod, $this> */
    public function taxPeriod(): BelongsTo
    {
        return $this->belongsTo(TaxPeriod::class);
    }

    /** @return BelongsTo<ObligationRuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(ObligationRuleVersion::class, 'obligation_rule_version_id');
    }

    /** @return BelongsTo<ObligationGenerationRun, $this> */
    public function previewRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'preview_run_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => GenerationRunStatus::class,
            'input_snapshot' => 'array',
            'parameter_snapshot' => 'array',
            'result_snapshot' => 'array',
            'statutory_due_date' => 'date',
            'internal_target_date' => 'date',
        ];
    }
}
