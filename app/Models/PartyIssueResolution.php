<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['party_issue_id', 'outcome', 'reason', 'resolved_by', 'resolved_at'])]
final class PartyIssueResolution extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Party issue resolutions are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Party issue resolutions are immutable.'));
    }

    /** @return BelongsTo<PartyIssue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(PartyIssue::class, 'party_issue_id');
    }

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
