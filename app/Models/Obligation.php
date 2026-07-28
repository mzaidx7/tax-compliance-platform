<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ObligationOrigin;
use App\Enums\ObligationStatus;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\ObligationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property string $obligation_type
 * @property string|null $period_label
 * @property Carbon $statutory_due_date
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
 */
#[Fillable([
    'client_id',
    'obligation_type',
    'period_label',
    'statutory_due_date',
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

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
            'internal_target_date' => 'date',
            'last_verified_on' => 'date',
            'origin' => ObligationOrigin::class,
            'status' => ObligationStatus::class,
        ];
    }
}
