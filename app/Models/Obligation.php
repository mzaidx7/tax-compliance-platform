<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ObligationOrigin;
use App\Enums\ObligationStatus;
use App\Models\Concerns\BelongsToFirm;
use Carbon\CarbonInterface;
use Database\Factories\ObligationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $client_id
 * @property string|null $client_service_enrollment_id
 * @property string|null $tax_period_id
 * @property string|null $obligation_rule_version_id
 * @property string|null $generation_run_id
 * @property string|null $generation_key
 * @property array<string, mixed>|null $calculation_input_snapshot
 * @property array<string, mixed>|null $calculation_parameter_snapshot
 * @property array<string, mixed>|null $calculation_result_snapshot
 * @property string|null $calculation_explanation
 * @property string $obligation_type
 * @property string|null $period_label
 * @property Carbon $statutory_due_date
 * @property Carbon|null $effective_due_date
 * @property Carbon|null $internal_target_date
 * @property ObligationOrigin $origin
 * @property ObligationStatus $status
 * @property string $source_reference
 * @property Carbon $last_verified_on
 * @property int $verified_by
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FilingRecord|null $filingRecord
 * @property-read PaymentRecord|null $paymentRecord
 * @property-read TaxRecord|null $taxRecord
 * @property-read Collection<int, ObligationDeadlineOverride> $deadlineOverrides
 */
#[Fillable([
    'client_id',
    'client_service_enrollment_id',
    'tax_period_id',
    'obligation_rule_version_id',
    'generation_run_id',
    'generation_key',
    'calculation_input_snapshot',
    'calculation_parameter_snapshot',
    'calculation_result_snapshot',
    'calculation_explanation',
    'obligation_type',
    'period_label',
    'statutory_due_date',
    'effective_due_date',
    'internal_target_date',
    'origin',
    'status',
    'source_reference',
    'last_verified_on',
    'verified_by',
    'created_by',
])]
final class Obligation extends Model
{
    /** @use HasFactory<ObligationFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    /** @var list<string> */
    private const GENERATED_SNAPSHOT_FIELDS = [
        'client_id',
        'client_service_enrollment_id',
        'tax_period_id',
        'obligation_rule_version_id',
        'generation_run_id',
        'generation_key',
        'calculation_input_snapshot',
        'calculation_parameter_snapshot',
        'calculation_result_snapshot',
        'calculation_explanation',
        'statutory_due_date',
        'internal_target_date',
    ];

    protected static function booted(): void
    {
        self::updating(function (self $obligation): void {
            if (
                $obligation->getRawOriginal('origin') === ObligationOrigin::GovernedRule->value
                && $obligation->isDirty(self::GENERATED_SNAPSHOT_FIELDS)
            ) {
                throw new \LogicException('Generated obligation snapshots are immutable.');
            }
        });
    }

    /**
     * @return BelongsTo<Client, $this>
     */
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
    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(ObligationGenerationRun::class, 'generation_run_id');
    }

    /** @return HasMany<ObligationDeadlineOverride, $this> */
    public function deadlineOverrides(): HasMany
    {
        return $this->hasMany(ObligationDeadlineOverride::class);
    }

    public function effectiveDueDate(): CarbonInterface
    {
        return $this->effective_due_date ?? $this->statutory_due_date;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * The primary work item. Linked follow-ups are reached through `workItems`.
     *
     * @return HasOne<WorkItem, $this>
     */
    public function workItem(): HasOne
    {
        return $this->hasOne(WorkItem::class)->whereNull('parent_work_item_id');
    }

    /**
     * The primary work item and every linked follow-up.
     *
     * @return HasMany<WorkItem, $this>
     */
    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    /**
     * Filing state is tracked separately from the work item that prepares it.
     *
     * @return HasOne<FilingRecord, $this>
     */
    public function filingRecord(): HasOne
    {
        return $this->hasOne(FilingRecord::class);
    }

    /**
     * Payment state is tracked separately from both work state and filing state.
     *
     * @return HasOne<PaymentRecord, $this>
     */
    public function paymentRecord(): HasOne
    {
        return $this->hasOne(PaymentRecord::class);
    }

    /**
     * Tax figures are tracked separately from work, filing and payment state.
     *
     * @return HasOne<TaxRecord, $this>
     */
    public function taxRecord(): HasOne
    {
        return $this->hasOne(TaxRecord::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statutory_due_date' => 'date',
            'effective_due_date' => 'date',
            'internal_target_date' => 'date',
            'last_verified_on' => 'date',
            'calculation_input_snapshot' => 'array',
            'calculation_parameter_snapshot' => 'array',
            'calculation_result_snapshot' => 'array',
            'origin' => ObligationOrigin::class,
            'status' => ObligationStatus::class,
        ];
    }
}
