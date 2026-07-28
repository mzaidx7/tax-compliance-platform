<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DuplicateSignalType;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'duplicate_candidate_id', 'signal_type', 'first_normalized_value', 'second_normalized_value',
    'normalizer_version', 'contribution_explanation', 'recorded_by', 'recorded_at',
])]
final class DuplicateCandidateSignal extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Duplicate candidate signals are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Duplicate candidate signals are immutable.'));
    }

    /** @return BelongsTo<DuplicateCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(DuplicateCandidate::class, 'duplicate_candidate_id');
    }

    protected function casts(): array
    {
        return ['signal_type' => DuplicateSignalType::class, 'recorded_at' => 'datetime'];
    }
}
