<?php

declare(strict_types=1);

namespace App\Actions\Operations;

use App\Actions\Audit\RecordAudit;
use App\Enums\OperationalFilterSurface;
use App\Enums\WorkItemStatus;
use App\Models\Client;
use App\Models\SavedOperationalFilter;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class SaveOperationalFilter
{
    public function __construct(private FirmContext $context, private RecordAudit $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(
        User $actor,
        OperationalFilterSurface $surface,
        mixed $name,
        array $filters,
        ?SavedOperationalFilter $existing = null,
    ): SavedOperationalFilter {
        $firmId = $this->context->firmId();
        if ($existing !== null && $existing->firm_id !== $firmId) {
            throw new AuthorizationException('The saved filter does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize($existing === null ? 'create' : 'update', $existing ?? SavedOperationalFilter::class);
        /** @var array{name: string} $validatedName */
        $validatedName = Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:80']])->validate();
        $validatedFilters = $this->validateFilters($surface, $filters, $firmId);

        return DB::transaction(function () use ($actor, $surface, $validatedName, $validatedFilters, $existing): SavedOperationalFilter {
            $filter = $existing ?? new SavedOperationalFilter;
            $filter->fill([
                'user_id' => $actor->id,
                'surface' => $surface,
                'name' => trim($validatedName['name']),
                'name_normalized' => mb_strtolower(trim($validatedName['name'])),
                'filters' => $validatedFilters,
            ]);
            $filter->save();
            $this->audit->handle($existing === null ? 'operational_filter.saved' : 'operational_filter.updated', $actor, $filter, [], [
                'surface' => $surface->value,
                'filter_keys' => array_keys($validatedFilters),
            ]);

            return $filter->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|null>
     */
    private function validateFilters(OperationalFilterSurface $surface, array $filters, string $firmId): array
    {
        if ($surface === OperationalFilterSurface::WorkRegister) {
            /** @var array{search: string|null, status: string|null} $validated */
            $validated = Validator::make($filters, [
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', Rule::enum(WorkItemStatus::class)],
            ])->validate();

            return ['search' => trim($validated['search'] ?? ''), 'status' => $validated['status'] ?? ''];
        }

        /** @var array{client_id: string|null, horizon_days: int} $validated */
        $validated = Validator::make($filters, [
            'client_id' => ['nullable', 'string', 'max:26'],
            'horizon_days' => ['required', 'integer', Rule::in([7, 14, 30, 60, 90])],
        ])->validate();
        if (($validated['client_id'] ?? null) !== null) {
            Client::query()->where('firm_id', $firmId)->findOrFail($validated['client_id']);
        }

        return ['client_id' => $validated['client_id'] ?? '', 'horizon_days' => $validated['horizon_days']];
    }
}
