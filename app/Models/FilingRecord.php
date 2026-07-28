<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FilingStatus;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\FilingRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $obligation_id
 * @property FilingStatus $status
 * @property string|null $filing_reference
 * @property Carbon|null $filed_on
 * @property int $created_by
 */
#[Fillable(['obligation_id', 'status', 'filing_reference', 'filed_on', 'created_by'])]
final class FilingRecord extends Model
{
    /** @use HasFactory<FilingRecordFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<FilingRecordTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(FilingRecordTransition::class);
    }

    /** @return list<FilingStatus> */
    public function allowedTransitions(): array
    {
        return $this->status->allowedTransitions();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => FilingStatus::class,
            'filed_on' => 'date',
        ];
    }
}
