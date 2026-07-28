<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaxRecordStatus;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\TaxRecordAmendmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $tax_record_id
 * @property TaxRecordStatus|null $previous_status
 * @property string|null $previous_taxable_amount
 * @property string|null $previous_tax_amount
 * @property TaxRecordStatus $new_status
 * @property string $new_taxable_amount
 * @property string $new_tax_amount
 * @property int $amended_by
 * @property string $reason
 * @property Carbon $amended_at
 */
#[Fillable([
    'tax_record_id',
    'previous_status',
    'previous_taxable_amount',
    'previous_tax_amount',
    'new_status',
    'new_taxable_amount',
    'new_tax_amount',
    'amended_by',
    'reason',
    'amended_at',
])]
final class TaxRecordAmendment extends Model
{
    /** @use HasFactory<TaxRecordAmendmentFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Tax record amendment history is append-only.');
        });

        self::deleting(function (): never {
            throw new LogicException('Tax record amendment history is append-only.');
        });
    }

    /** @return BelongsTo<TaxRecord, $this> */
    public function taxRecord(): BelongsTo
    {
        return $this->belongsTo(TaxRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amended_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'previous_status' => TaxRecordStatus::class,
            'new_status' => TaxRecordStatus::class,
            'previous_taxable_amount' => 'decimal:2',
            'previous_tax_amount' => 'decimal:2',
            'new_taxable_amount' => 'decimal:2',
            'new_tax_amount' => 'decimal:2',
            'amended_at' => 'datetime',
        ];
    }
}
