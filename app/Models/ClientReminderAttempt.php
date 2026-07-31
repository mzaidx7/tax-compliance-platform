<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['client_reminder_request_id', 'status', 'failure_code', 'attempted_at'])]
final class ClientReminderAttempt extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Client reminder attempts are append-only.'));
        self::deleting(fn (): never => throw new LogicException('Client reminder attempts are append-only.'));
    }

    /** @return BelongsTo<ClientReminderRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ClientReminderRequest::class, 'client_reminder_request_id');
    }

    protected function casts(): array
    {
        return ['attempted_at' => 'immutable_datetime'];
    }
}
