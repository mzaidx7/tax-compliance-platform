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

#[Fillable(['calculator_key', 'version', 'name', 'status', 'prepared_by', 'approved_by', 'approved_at'])]
final class CalculatorGoldenCaseSet extends Model
{
    use BelongsToFirm, HasUlids;

    protected static function booted(): void
    {
        self::updating(function (self $set): void {
            if ($set->getRawOriginal('status') !== 'draft' || $set->status !== 'approved') {
                throw new LogicException('Golden-case sets may only move from draft to approved.');
            }
            if ($set->isDirty(['calculator_key', 'version', 'name', 'prepared_by'])) {
                throw new LogicException('Golden-case set identity is immutable.');
            }
        });
        self::deleting(fn (): never => throw new LogicException('Golden-case sets cannot be deleted.'));
    }

    /** @return HasMany<CalculatorGoldenCase, $this> */
    public function cases(): HasMany
    {
        return $this->hasMany(CalculatorGoldenCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'approved_at' => 'datetime'];
    }
}
