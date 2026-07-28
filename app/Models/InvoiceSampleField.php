<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceSampleFieldKey;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['invoice_readiness_sample_id', 'field_key', 'supplied_value', 'source_reference', 'recorded_by', 'recorded_at'])]
final class InvoiceSampleField extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Invoice sample fields are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Invoice sample fields are immutable.'));
    }

    /** @return BelongsTo<InvoiceReadinessSample, $this> */
    public function sample(): BelongsTo
    {
        return $this->belongsTo(InvoiceReadinessSample::class, 'invoice_readiness_sample_id');
    }

    protected function casts(): array
    {
        return ['field_key' => InvoiceSampleFieldKey::class, 'recorded_at' => 'datetime'];
    }
}
