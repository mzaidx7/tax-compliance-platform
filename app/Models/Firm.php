<?php

namespace App\Models;

use App\Enums\FirmStatus;
use Database\Factories\FirmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property FirmStatus $status
 * @property Carbon|null $suspended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'timezone', 'status', 'suspended_at'])]
class Firm extends Model
{
    /** @use HasFactory<FirmFactory> */
    use HasFactory, HasUlids;

    /**
     * @return HasMany<FirmMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(FirmMembership::class);
    }

    /**
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * @return HasMany<Obligation, $this>
     */
    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class);
    }

    /**
     * @return BelongsToMany<User, $this, FirmMembership, 'membership'>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'firm_users')
            ->using(FirmMembership::class)
            ->as('membership')
            ->withPivot(['id', 'role', 'status', 'joined_at', 'suspended_at', 'revoked_at'])
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FirmStatus::class,
            'suspended_at' => 'datetime',
        ];
    }
}
