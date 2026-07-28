<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PartyFieldKey;
use App\Enums\PartyFieldVerificationState;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $party_record_id
 * @property PartyFieldKey $field_key
 * @property string $value
 * @property PartyFieldVerificationState $verification_state
 * @property string|null $supersedes_party_field_version_id
 */
#[Fillable([
    'party_record_id', 'field_key', 'value', 'verification_state', 'source_kind',
    'source_reference', 'supersedes_party_field_version_id', 'recorded_by', 'recorded_at',
])]
final class PartyFieldVersion extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Party field versions are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Party field versions are immutable.'));
    }

    /** @return BelongsTo<PartyRecord, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(PartyRecord::class, 'party_record_id');
    }

    /** @return BelongsTo<PartyFieldVersion, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_party_field_version_id');
    }

    protected function casts(): array
    {
        return ['field_key' => PartyFieldKey::class, 'verification_state' => PartyFieldVerificationState::class, 'recorded_at' => 'datetime'];
    }
}
