<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['client_id', 'reference', 'is_customer', 'is_supplier', 'is_active', 'created_by'])]
final class PartyRecord extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Party records are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Party records are immutable.'));
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<PartyFieldVersion, $this> */
    public function fieldVersions(): HasMany
    {
        return $this->hasMany(PartyFieldVersion::class);
    }

    /** @return HasMany<PartyCorrectionProposal, $this> */
    public function correctionProposals(): HasMany
    {
        return $this->hasMany(PartyCorrectionProposal::class);
    }

    public function currentField(string $fieldKey): ?PartyFieldVersion
    {
        return $this->fieldVersions()->where('field_key', $fieldKey)->latest('recorded_at')->latest('id')->first();
    }

    protected function casts(): array
    {
        return ['is_customer' => 'boolean', 'is_supplier' => 'boolean', 'is_active' => 'boolean'];
    }
}
