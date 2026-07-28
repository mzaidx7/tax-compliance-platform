<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['rule_change_proposal_id', 'decision', 'replacement_obligation_id', 'reason', 'decided_by', 'decided_at'])]
final class RuleChangeProposalDecision extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Rule change proposal decisions are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Rule change proposal decisions are immutable.'));
    }

    /** @return BelongsTo<RuleChangeProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(RuleChangeProposal::class, 'rule_change_proposal_id');
    }

    /** @return BelongsTo<Obligation, $this> */
    public function replacementObligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class, 'replacement_obligation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }
}
