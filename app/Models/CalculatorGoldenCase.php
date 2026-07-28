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
 * @property array<string, mixed> $input_snapshot
 * @property array<string, mixed> $parameter_snapshot
 * @property array<string, mixed> $expected_result_snapshot
 */
#[Fillable([
    'calculator_golden_case_set_id',
    'name',
    'input_snapshot',
    'parameter_snapshot',
    'expected_result_snapshot',
    'official_source_title',
    'official_source_url',
    'source_verified_on',
    'prepared_by',
])]
final class CalculatorGoldenCase extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Calculator golden cases are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Calculator golden cases are immutable.'));
    }

    /** @return BelongsTo<CalculatorGoldenCaseSet, $this> */
    public function caseSet(): BelongsTo
    {
        return $this->belongsTo(CalculatorGoldenCaseSet::class, 'calculator_golden_case_set_id');
    }

    /** @return HasMany<CalculatorGoldenCaseVerification, $this> */
    public function verifications(): HasMany
    {
        return $this->hasMany(CalculatorGoldenCaseVerification::class);
    }

    /** @return BelongsTo<User, $this> */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    protected function casts(): array
    {
        return [
            'input_snapshot' => 'array',
            'parameter_snapshot' => 'array',
            'expected_result_snapshot' => 'array',
            'source_verified_on' => 'date',
        ];
    }
}
