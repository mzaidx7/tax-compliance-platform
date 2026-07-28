<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\PaymentRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $obligation_id
 * @property PaymentStatus $status
 * @property string|null $payment_reference
 * @property Carbon|null $paid_on
 * @property int $created_by
 */
#[Fillable(['obligation_id', 'status', 'payment_reference', 'paid_on', 'created_by'])]
final class PaymentRecord extends Model
{
    /** @use HasFactory<PaymentRecordFactory> */
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

    /** @return HasMany<PaymentRecordTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(PaymentRecordTransition::class);
    }

    /** @return list<PaymentStatus> */
    public function allowedTransitions(): array
    {
        return $this->status->allowedTransitions();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'paid_on' => 'date',
        ];
    }
}
