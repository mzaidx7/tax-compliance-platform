<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable(['party_record_id', 'current_party_field_version_id', 'proposed_value', 'evidence_note', 'proposed_by', 'proposed_at'])]
final class PartyCorrectionProposal extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Party correction proposals are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Party correction proposals are immutable.'));
    }

    /** @return BelongsTo<PartyRecord, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(PartyRecord::class, 'party_record_id');
    }

    /** @return BelongsTo<PartyFieldVersion, $this> */
    public function currentFieldVersion(): BelongsTo
    {
        return $this->belongsTo(PartyFieldVersion::class, 'current_party_field_version_id');
    }

    /** @return HasOne<PartyCorrectionDecision, $this> */
    public function decision(): HasOne
    {
        return $this->hasOne(PartyCorrectionDecision::class);
    }

    protected function casts(): array
    {
        return ['proposed_at' => 'datetime'];
    }
}
