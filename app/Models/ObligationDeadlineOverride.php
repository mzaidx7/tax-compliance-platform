<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'obligation_id',
    'previous_effective_due_date',
    'new_effective_due_date',
    'reason',
    'overridden_by',
    'overridden_at',
])]
final class ObligationDeadlineOverride extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Obligation deadline override history is append-only.');
        });
        self::deleting(function (): never {
            throw new LogicException('Obligation deadline override history is append-only.');
        });
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'previous_effective_due_date' => 'date',
            'new_effective_due_date' => 'date',
            'overridden_at' => 'datetime',
        ];
    }
}
