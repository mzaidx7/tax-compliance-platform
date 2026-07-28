<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['notification_id', 'read_by', 'read_at'])]
final class NotificationReadReceipt extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Notification read receipts are append-only.'));
        self::deleting(fn (): never => throw new LogicException('Notification read receipts are append-only.'));
    }

    /** @return BelongsTo<NotificationRequest, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(NotificationRequest::class, 'notification_id');
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
