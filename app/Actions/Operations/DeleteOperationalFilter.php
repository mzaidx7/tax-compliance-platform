<?php

declare(strict_types=1);

namespace App\Actions\Operations;

use App\Actions\Audit\RecordAudit;
use App\Models\SavedOperationalFilter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteOperationalFilter
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $actor, SavedOperationalFilter $filter): void
    {
        Gate::forUser($actor)->authorize('delete', $filter);

        DB::transaction(function () use ($actor, $filter): void {
            $this->audit->handle('operational_filter.deleted', $actor, $filter, [], ['surface' => $filter->surface->value]);
            $filter->delete();
        }, 3);
    }
}
