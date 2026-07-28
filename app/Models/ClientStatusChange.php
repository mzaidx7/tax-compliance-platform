<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientStatus;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['client_id', 'previous_status', 'new_status', 'changed_by', 'reason', 'changed_at'])]
final class ClientStatusChange extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Client status history is append-only.');
        });
        self::deleting(function (): never {
            throw new LogicException('Client status history is append-only.');
        });
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'previous_status' => ClientStatus::class,
            'new_status' => ClientStatus::class,
            'changed_at' => 'datetime',
        ];
    }
}
