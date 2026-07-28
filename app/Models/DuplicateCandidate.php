<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable(['candidate_key', 'first_party_record_id', 'second_party_record_id', 'recorded_by', 'recorded_at'])]
final class DuplicateCandidate extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Duplicate candidates are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Duplicate candidates are immutable.'));
    }

    /** @return BelongsTo<PartyRecord, $this> */
    public function firstParty(): BelongsTo
    {
        return $this->belongsTo(PartyRecord::class, 'first_party_record_id');
    }

    /** @return BelongsTo<PartyRecord, $this> */
    public function secondParty(): BelongsTo
    {
        return $this->belongsTo(PartyRecord::class, 'second_party_record_id');
    }

    /** @return HasMany<DuplicateCandidateSignal, $this> */
    public function signals(): HasMany
    {
        return $this->hasMany(DuplicateCandidateSignal::class);
    }

    /** @return HasOne<DuplicateCandidateDecision, $this> */
    public function decision(): HasOne
    {
        return $this->hasOne(DuplicateCandidateDecision::class);
    }

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }
}
