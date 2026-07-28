<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaxRecordStatus;
use App\Enums\TaxType;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\TaxRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $obligation_id
 * @property TaxType $tax_type
 * @property string $period_label
 * @property string $currency
 * @property string $taxable_amount
 * @property string $tax_amount
 * @property TaxRecordStatus $status
 * @property int $created_by
 */
#[Fillable([
    'obligation_id',
    'tax_type',
    'period_label',
    'currency',
    'taxable_amount',
    'tax_amount',
    'status',
    'created_by',
])]
final class TaxRecord extends Model
{
    /** @use HasFactory<TaxRecordFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TaxRecordAmendment, $this> */
    public function amendments(): HasMany
    {
        return $this->hasMany(TaxRecordAmendment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tax_type' => TaxType::class,
            'status' => TaxRecordStatus::class,
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }
}
