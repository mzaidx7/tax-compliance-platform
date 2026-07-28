<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpiryReminderKind;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['client_document_id', 'kind', 'scheduled_for', 'days_from_expiry', 'generated_at'])]
final class DocumentExpiryReminder extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Document expiry reminders are append-only.'));
        self::deleting(fn (): never => throw new LogicException('Document expiry reminders are append-only.'));
    }

    /** @return BelongsTo<ClientDocument, $this> */
    public function clientDocument(): BelongsTo
    {
        return $this->belongsTo(ClientDocument::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => ExpiryReminderKind::class,
            'scheduled_for' => 'date',
            'days_from_expiry' => 'integer',
            'generated_at' => 'datetime',
        ];
    }
}
