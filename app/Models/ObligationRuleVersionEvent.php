<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RuleVersionStatus;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['obligation_rule_version_id', 'from_status', 'to_status', 'acted_by', 'reason', 'occurred_at'])]
final class ObligationRuleVersionEvent extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Rule lifecycle history is append-only.'));
        self::deleting(fn (): never => throw new LogicException('Rule lifecycle history is append-only.'));
    }

    /** @return BelongsTo<ObligationRuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(ObligationRuleVersion::class, 'obligation_rule_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => RuleVersionStatus::class,
            'to_status' => RuleVersionStatus::class,
            'occurred_at' => 'datetime',
        ];
    }
}
