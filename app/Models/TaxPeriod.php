<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaxPeriodStatus;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $tax_registration_id
 * @property string $label
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property TaxPeriodStatus $status
 * @property-read TaxRegistration $registration
 */
#[Fillable(['tax_registration_id', 'label', 'starts_on', 'ends_on', 'status', 'created_by'])]
final class TaxPeriod extends Model
{
    use BelongsToFirm, HasUlids;

    /** @return BelongsTo<TaxRegistration, $this> */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(TaxRegistration::class, 'tax_registration_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => TaxPeriodStatus::class,
        ];
    }
}
