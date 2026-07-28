<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Feature;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\FeatureFlagOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $firm_id
 * @property Feature $feature
 * @property bool $enabled
 * @property int $created_by
 * @property int $updated_by
 */
#[Fillable(['feature', 'enabled', 'created_by', 'updated_by'])]
final class FeatureFlagOverride extends Model
{
    /** @use HasFactory<FeatureFlagOverrideFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'feature' => Feature::class,
            'enabled' => 'boolean',
        ];
    }
}
