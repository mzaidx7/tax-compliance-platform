<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationFinalStatus;
use App\Enums\NotificationRequestStatus;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\NotificationRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property int $recipient_user_id
 * @property string $template_key
 * @property int $template_version
 * @property NotificationChannel $channel
 * @property string $deterministic_key
 * @property string|null $trigger_type
 * @property string|null $trigger_id
 * @property Carbon $scheduled_at
 * @property NotificationRequestStatus $status
 * @property NotificationFinalStatus|null $final_status
 * @property int $attempt_count
 * @property string $correlation_id
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'recipient_user_id',
    'template_key',
    'template_version',
    'channel',
    'deterministic_key',
    'trigger_type',
    'trigger_id',
    'scheduled_at',
    'status',
    'final_status',
    'attempt_count',
    'correlation_id',
    'completed_at',
])]
class NotificationRequest extends Model
{
    /** @use HasFactory<NotificationRequestFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    protected $table = 'notifications';

    protected static function booted(): void
    {
        static::updating(function (self $notification): void {
            if ($notification->isDirty([
                'firm_id',
                'recipient_user_id',
                'template_key',
                'template_version',
                'channel',
                'deterministic_key',
                'trigger_type',
                'trigger_id',
                'scheduled_at',
                'correlation_id',
            ])) {
                throw new LogicException('Notification request identity is immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Notification requests cannot be deleted before retention policy approval.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * @return HasMany<NotificationAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(NotificationAttempt::class, 'notification_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_version' => 'integer',
            'channel' => NotificationChannel::class,
            'scheduled_at' => 'immutable_datetime',
            'status' => NotificationRequestStatus::class,
            'final_status' => NotificationFinalStatus::class,
            'attempt_count' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
