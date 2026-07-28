<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReadinessDataDomain;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $key
 * @property string $name
 * @property ReadinessDataDomain $data_domain
 * @property string $field_or_scenario
 */
#[Fillable(['key', 'name', 'data_domain', 'field_or_scenario', 'created_by'])]
final class DataQualityRuleDefinition extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Readiness rule definitions are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Readiness rule definitions are immutable.'));
    }

    /** @return HasMany<DataQualityRuleVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DataQualityRuleVersion::class);
    }

    protected function casts(): array
    {
        return ['data_domain' => ReadinessDataDomain::class];
    }
}
