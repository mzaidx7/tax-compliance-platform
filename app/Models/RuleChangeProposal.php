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

#[Fillable([
    'original_obligation_id',
    'proposed_rule_version_id',
    'preview_run_id',
    'original_statutory_due_date',
    'proposed_statutory_due_date',
    'reason',
    'proposed_by',
    'proposed_at',
])]
final class RuleChangeProposal extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Rule change proposals are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Rule change proposals are immutable.'));
    }

    /** @return BelongsTo<Obligation, $this> */
    public function originalObligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class, 'original_obligation_id');
    }

    /** @return BelongsTo<ObligationRuleVersion, $this> */
    public function proposedRuleVersion(): BelongsTo
    {
        return $this->belongsTo(ObligationRuleVersion::class, 'proposed_rule_version_id');
    }

    /** @return BelongsTo<ObligationGenerationRun, $this> */
    public function previewRun(): BelongsTo
    {
        return $this->belongsTo(ObligationGenerationRun::class, 'preview_run_id');
    }

    /** @return HasOne<RuleChangeProposalDecision, $this> */
    public function decision(): HasOne
    {
        return $this->hasOne(RuleChangeProposalDecision::class);
    }

    protected function casts(): array
    {
        return [
            'original_statutory_due_date' => 'date',
            'proposed_statutory_due_date' => 'date',
            'proposed_at' => 'datetime',
        ];
    }
}
