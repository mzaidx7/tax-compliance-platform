<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientStatus;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\ClientFactory;
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
 * @property string $internal_code
 * @property string $internal_code_normalized
 * @property string $legal_name
 * @property string|null $trade_name
 * @property string|null $entity_type
 * @property ClientStatus $status
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'internal_code',
    'internal_code_normalized',
    'legal_name',
    'trade_name',
    'entity_type',
    'status',
    'created_by',
])]
final class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Obligation, $this>
     */
    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
        ];
    }
}
