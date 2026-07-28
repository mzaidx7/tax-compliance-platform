<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['client_id', 'sample_reference', 'source_reference', 'recorded_by', 'recorded_at'])]
final class InvoiceReadinessSample extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Invoice readiness samples are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Invoice readiness samples are immutable.'));
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<InvoiceSampleField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(InvoiceSampleField::class);
    }

    /** @return HasMany<InvoiceReadinessIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(InvoiceReadinessIssue::class);
    }

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }
}
