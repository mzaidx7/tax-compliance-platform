<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $key
 * @property string $name
 * @property string $obligation_type
 * @property string $jurisdiction
 * @property string $authority
 * @property int $created_by
 */
#[Fillable(['key', 'name', 'obligation_type', 'jurisdiction', 'authority', 'created_by'])]
final class ObligationRuleTemplate extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Rule templates are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Rule templates are immutable.'));
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ObligationRuleVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ObligationRuleVersion::class);
    }
}
