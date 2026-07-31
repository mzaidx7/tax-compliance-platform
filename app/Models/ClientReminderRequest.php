<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientReminderCategory;
use App\Enums\ClientReminderStatus;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $client_id
 * @property ClientReminderCategory $category
 * @property ClientReminderStatus $status
 * @property string $source_type
 * @property string $source_id
 * @property Carbon $event_date
 * @property int $days_before
 * @property Carbon $scheduled_for
 * @property string $deterministic_key
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $sent_at
 * @property string|null $failure_code
 * @property-read Client $client
 * @property-read Model|null $source
 */
#[Fillable([
    'client_id', 'category', 'status', 'source_type', 'source_id', 'event_date', 'days_before',
    'scheduled_for', 'deterministic_key', 'reviewed_by', 'reviewed_at', 'sent_at', 'failure_code',
])]
final class ClientReminderRequest extends Model
{
    use BelongsToFirm, HasUlids;

    protected static function booted(): void
    {
        self::updating(function (self $request): void {
            if ($request->isDirty([
                'firm_id', 'client_id', 'category', 'source_type', 'source_id', 'event_date',
                'days_before', 'scheduled_for', 'deterministic_key',
            ])) {
                throw new LogicException('Client reminder identity is immutable.');
            }
        });

        self::deleting(fn (): never => throw new LogicException('Client reminders cannot be deleted.'));
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<ClientReminderAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(ClientReminderAttempt::class);
    }

    protected function casts(): array
    {
        return [
            'category' => ClientReminderCategory::class,
            'status' => ClientReminderStatus::class,
            'event_date' => 'date',
            'scheduled_for' => 'date',
            'reviewed_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'days_before' => 'integer',
        ];
    }
}
