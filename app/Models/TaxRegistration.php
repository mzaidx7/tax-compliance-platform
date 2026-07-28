<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaxRegistrationStatus;
use App\Enums\TaxType;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property TaxType $tax_type
 * @property TaxRegistrationStatus $status
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 */
#[Fillable([
    'client_id',
    'tax_type',
    'registration_number',
    'registration_number_normalized',
    'status',
    'effective_from',
    'effective_to',
    'created_by',
])]
final class TaxRegistration extends Model
{
    use BelongsToFirm, HasUlids;

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<TaxPeriod, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(TaxPeriod::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tax_type' => TaxType::class,
            'status' => TaxRegistrationStatus::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}
