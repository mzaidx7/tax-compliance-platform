<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Database\Factories\ChecklistTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['template_key', 'name', 'created_by'])]
final class ChecklistTemplate extends Model
{
    public const CORE_KEY = 'core-compliance-work';

    /** @use HasFactory<ChecklistTemplateFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    /** @return HasMany<ChecklistVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ChecklistVersion::class);
    }
}
