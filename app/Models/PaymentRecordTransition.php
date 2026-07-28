<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\PaymentRecordTransitionFactory;
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
 * @property string $payment_record_id
 * @property PaymentStatus|null $from_status
 * @property PaymentStatus $to_status
 * @property int $transitioned_by
 * @property string $reason
 * @property Carbon $transitioned_at
 */
#[Fillable([
    'payment_record_id',
    'from_status',
    'to_status',
    'transitioned_by',
    'reason',
    'transitioned_at',
])]
final class PaymentRecordTransition extends Model
{
    /** @use HasFactory<PaymentRecordTransitionFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Payment record transition history is append-only.');
        });

        self::deleting(function (): never {
            throw new LogicException('Payment record transition history is append-only.');
        });
    }

    /** @return BelongsTo<PaymentRecord, $this> */
    public function paymentRecord(): BelongsTo
    {
        return $this->belongsTo(PaymentRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transitioned_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => PaymentStatus::class,
            'to_status' => PaymentStatus::class,
            'transitioned_at' => 'datetime',
        ];
    }
}
