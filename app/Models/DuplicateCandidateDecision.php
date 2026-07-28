<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DuplicateDecisionOutcome;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['duplicate_candidate_id', 'outcome', 'reason', 'decided_by', 'decided_at'])]
final class DuplicateCandidateDecision extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Duplicate candidate decisions are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Duplicate candidate decisions are immutable.'));
    }

    /** @return BelongsTo<DuplicateCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(DuplicateCandidate::class, 'duplicate_candidate_id');
    }

    protected function casts(): array
    {
        return ['outcome' => DuplicateDecisionOutcome::class, 'decided_at' => 'datetime'];
    }
}
