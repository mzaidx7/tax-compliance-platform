<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ObligationStatus;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['obligation_id', 'previous_status', 'new_status', 'replacement_obligation_id', 'reason', 'recorded_by', 'recorded_at'])]
final class ObligationDisposition extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Obligation disposition history is append-only.');
        });
        self::deleting(function (): never {
            throw new LogicException('Obligation disposition history is append-only.');
        });
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<Obligation, $this> */
    public function replacement(): BelongsTo
    {
        return $this->belongsTo(Obligation::class, 'replacement_obligation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function casts(): array
    {
        return ['previous_status' => ObligationStatus::class, 'new_status' => ObligationStatus::class, 'recorded_at' => 'datetime'];
    }
}
