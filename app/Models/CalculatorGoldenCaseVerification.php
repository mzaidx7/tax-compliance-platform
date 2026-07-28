<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['calculator_golden_case_id', 'observed_result_snapshot', 'calculation_explanation', 'passed', 'verified_by', 'verified_at'])]
final class CalculatorGoldenCaseVerification extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Golden-case verification evidence is immutable.'));
        self::deleting(fn (): never => throw new LogicException('Golden-case verification evidence is immutable.'));
    }

    /** @return BelongsTo<CalculatorGoldenCase, $this> */
    public function goldenCase(): BelongsTo
    {
        return $this->belongsTo(CalculatorGoldenCase::class, 'calculator_golden_case_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    protected function casts(): array
    {
        return ['observed_result_snapshot' => 'array', 'passed' => 'boolean', 'verified_at' => 'datetime'];
    }
}
