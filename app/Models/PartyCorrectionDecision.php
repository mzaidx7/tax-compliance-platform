<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['party_correction_proposal_id', 'decision', 'new_party_field_version_id', 'reason', 'decided_by', 'decided_at'])]
final class PartyCorrectionDecision extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Party correction decisions are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Party correction decisions are immutable.'));
    }

    /** @return BelongsTo<PartyCorrectionProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(PartyCorrectionProposal::class, 'party_correction_proposal_id');
    }

    /** @return BelongsTo<PartyFieldVersion, $this> */
    public function newFieldVersion(): BelongsTo
    {
        return $this->belongsTo(PartyFieldVersion::class, 'new_party_field_version_id');
    }

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }
}
