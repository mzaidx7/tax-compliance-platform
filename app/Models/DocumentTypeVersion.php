<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $key
 * @property int $version
 * @property string $name
 * @property bool $expiry_required
 * @property list<int> $reminder_days
 * @property int|null $overdue_repeat_days
 * @property Carbon $published_at
 * @property int $created_by
 */
#[Fillable([
    'key',
    'version',
    'name',
    'expiry_required',
    'reminder_days',
    'overdue_repeat_days',
    'published_at',
    'created_by',
])]
final class DocumentTypeVersion extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Published document type versions are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Published document type versions are immutable.'));
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ClientDocument, $this> */
    public function clientDocuments(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'expiry_required' => 'boolean',
            'reminder_days' => 'array',
            'overdue_repeat_days' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
