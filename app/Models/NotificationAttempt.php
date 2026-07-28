<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationAttemptStatus;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\NotificationAttemptFactory;
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
 * @property string $notification_id
 * @property int $attempt_number
 * @property NotificationAttemptStatus $status
 * @property string|null $provider_reference
 * @property string|null $failure_reason
 * @property Carbon $attempted_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'notification_id',
    'attempt_number',
    'status',
    'provider_reference',
    'failure_reason',
    'attempted_at',
])]
class NotificationAttempt extends Model
{
    /** @use HasFactory<NotificationAttemptFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Notification attempts are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Notification attempts are append-only.');
        });
    }

    /**
     * @return BelongsTo<NotificationRequest, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(NotificationRequest::class, 'notification_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'status' => NotificationAttemptStatus::class,
            'attempted_at' => 'immutable_datetime',
        ];
    }
}
