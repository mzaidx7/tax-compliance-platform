<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RuleVersionStatus;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $obligation_rule_template_id
 * @property int $version
 * @property RuleVersionStatus $status
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $applicability_criteria
 * @property string $calculator_key
 * @property array<string, mixed> $parameters
 * @property string $official_source_title
 * @property string $official_source_url
 * @property Carbon|null $source_published_on
 * @property Carbon|null $source_last_verified_on
 * @property int $prepared_by
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $published_at
 * @property string $change_summary
 * @property-read ObligationRuleTemplate $template
 */
#[Fillable([
    'obligation_rule_template_id',
    'version',
    'status',
    'effective_from',
    'effective_to',
    'applicability_criteria',
    'calculator_key',
    'parameters',
    'official_source_title',
    'official_source_url',
    'source_published_on',
    'source_last_verified_on',
    'prepared_by',
    'verified_by',
    'verified_at',
    'approved_at',
    'published_at',
    'change_summary',
])]
final class ObligationRuleVersion extends Model
{
    use BelongsToFirm, HasUlids;

    /** @var list<string> */
    private const CONTENT_FIELDS = [
        'effective_from',
        'effective_to',
        'applicability_criteria',
        'calculator_key',
        'parameters',
        'official_source_title',
        'official_source_url',
        'source_published_on',
        'change_summary',
    ];

    protected static function booted(): void
    {
        self::updating(function (self $version): void {
            $from = RuleVersionStatus::from((string) $version->getRawOriginal('status'));
            $to = $version->status;

            if ($from !== RuleVersionStatus::Draft && $version->isDirty(self::CONTENT_FIELDS)) {
                throw new LogicException('Rule version content is immutable after draft.');
            }

            if ($from !== $to && ! self::transitionAllowed($from, $to)) {
                throw new LogicException('Invalid rule version lifecycle transition.');
            }
        });

        self::deleting(fn (): never => throw new LogicException('Rule versions cannot be deleted.'));
    }

    /** @return BelongsTo<ObligationRuleTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ObligationRuleTemplate::class, 'obligation_rule_template_id');
    }

    /** @return BelongsTo<User, $this> */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return HasMany<ObligationRuleVersionEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ObligationRuleVersionEvent::class);
    }

    private static function transitionAllowed(RuleVersionStatus $from, RuleVersionStatus $to): bool
    {
        return match ($from) {
            RuleVersionStatus::Draft => $to === RuleVersionStatus::UnderReview,
            RuleVersionStatus::UnderReview => $to === RuleVersionStatus::Approved,
            RuleVersionStatus::Approved => $to === RuleVersionStatus::Published,
            RuleVersionStatus::Published => in_array($to, [RuleVersionStatus::Superseded, RuleVersionStatus::Retired], true),
            RuleVersionStatus::Superseded, RuleVersionStatus::Retired => false,
        };
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => RuleVersionStatus::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
            'parameters' => 'array',
            'source_published_on' => 'date',
            'source_last_verified_on' => 'date',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
