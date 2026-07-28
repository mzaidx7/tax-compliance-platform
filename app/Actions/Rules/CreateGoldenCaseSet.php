<?php

declare(strict_types=1);

namespace App\Actions\Rules;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\CalculatorGoldenCaseSet;
use App\Models\Obligation;
use App\Models\User;
use App\Support\CalculatorRegistry;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final readonly class CreateGoldenCaseSet
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private CalculatorRegistry $calculatorRegistry,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, mixed $calculatorKey, mixed $name): CalculatorGoldenCaseSet
    {
        $firmId = $this->authorize($actor);
        /** @var array{calculatorKey: string, name: string} $validated */
        $validated = Validator::make(compact('calculatorKey', 'name'), [
            'calculatorKey' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
        ])->validate();
        $this->calculatorRegistry->get($validated['calculatorKey']);

        return DB::transaction(function () use ($actor, $firmId, $validated): CalculatorGoldenCaseSet {
            $version = ((int) CalculatorGoldenCaseSet::query()
                ->where('calculator_key', $validated['calculatorKey'])
                ->lockForUpdate()
                ->max('version')) + 1;
            $set = CalculatorGoldenCaseSet::query()->create([
                'calculator_key' => $validated['calculatorKey'],
                'version' => $version,
                'name' => trim($validated['name']),
                'status' => 'draft',
                'prepared_by' => $actor->id,
            ]);
            $this->recordAudit->handle(
                action: 'calculator_golden_case_set.created',
                actor: $actor,
                auditable: $set,
                after: ['calculator_key' => $set->calculator_key, 'version' => $version, 'firm_id' => $firmId],
            );

            return $set->refresh();
        }, 3);
    }

    private function authorize(User $actor): string
    {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        Gate::forUser($actor)->authorize('create', Obligation::class);

        return $firmId;
    }
}
