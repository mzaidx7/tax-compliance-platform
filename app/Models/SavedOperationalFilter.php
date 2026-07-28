<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OperationalFilterSurface;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $firm_id
 * @property int $user_id
 * @property OperationalFilterSurface $surface
 * @property string $name
 * @property string $name_normalized
 * @property array<string, string|int|null> $filters
 */
#[Fillable(['user_id', 'surface', 'name', 'name_normalized', 'filters'])]
final class SavedOperationalFilter extends Model
{
    use BelongsToFirm, HasUlids;

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return ['surface' => OperationalFilterSurface::class, 'filters' => 'array'];
    }
}
