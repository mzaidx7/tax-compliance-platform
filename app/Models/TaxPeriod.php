<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaxPeriodStatus;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
