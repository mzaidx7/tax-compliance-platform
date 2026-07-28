<?php

namespace App\Models\Concerns;

use App\Models\Firm;
use App\Models\Scopes\FirmScope;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToFirm
{
    public static function bootBelongsToFirm(): void
    {
        static::addGlobalScope(new FirmScope);

        static::saving(function (Model $model): void {
            $firmId = app(FirmContext::class)->firmId()
                ?? throw new LogicException('Tenant-owned records require an active firm context.');

            $assignedFirmId = $model->getAttribute('firm_id');

            if ($assignedFirmId !== null && (string) $assignedFirmId !== $firmId) {
                throw new LogicException('A tenant-owned record cannot be assigned to another firm.');
            }

            if ($model->exists && $model->isDirty('firm_id')) {
                throw new LogicException('A tenant-owned record cannot be moved between firms.');
            }

            $model->setAttribute('firm_id', $firmId);
        });
    }

    /**
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }
}
