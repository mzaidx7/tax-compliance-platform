<?php

declare(strict_types=1);

namespace App\Actions\Rules;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\CalculatorGoldenCase;
use App\Models\CalculatorGoldenCaseSet;
use App\Models\Obligation;
use App\Models\User;
use App\Support\CalculatorRegistry;
use App\Support\FeatureFlags;
use App\Support\OfficialSourceUrl;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class AddGoldenCase
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private CalculatorRegistry $calculatorRegistry,
        private OfficialSourceUrl $officialSourceUrl,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $parameters
     */
    public function handle(
        User $actor,
        CalculatorGoldenCaseSet $set,
        mixed $name,
        array $inputs,
        array $parameters,
        mixed $expectedStatutoryDueDate,
        mixed $officialSourceTitle,
        mixed $officialSourceUrl,
        mixed $sourceVerifiedOn,
    ): CalculatorGoldenCase {
        $this->authorize($actor, $set);
        /** @var array{name: string, expected: string, sourceTitle: string, sourceUrl: string, verifiedOn: string} $validated */
        $validated = Validator::make([
            'name' => $name,
            'expected' => $expectedStatutoryDueDate,
            'sourceTitle' => $officialSourceTitle,
            'sourceUrl' => $officialSourceUrl,
            'verifiedOn' => $sourceVerifiedOn,
        ], [
            'name' => ['required', 'string', 'max:150'],
            'expected' => ['required', 'date_format:Y-m-d'],
            'sourceTitle' => ['required', 'string', 'max:255'],
            'sourceUrl' => ['required', 'url:https', 'max:2000'],
            'verifiedOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ])->validate();
        if (! $this->officialSourceUrl->allowed($validated['sourceUrl'])) {
            throw ValidationException::withMessages(['officialSourceUrl' => 'Use an HTTPS source on a configured official UAE government host.']);
        }
        $calculator = $this->calculatorRegistry->get($set->calculator_key);
        $validatedParameters = $calculator->validateParameters($parameters);
        $calculator->calculate($inputs, $validatedParameters);

        return DB::transaction(function () use ($actor, $set, $validated, $inputs, $validatedParameters): CalculatorGoldenCase {
            $locked = CalculatorGoldenCaseSet::query()->lockForUpdate()->findOrFail($set->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['caseSet' => 'Cases can be added only to a draft set.']);
            }
            $case = CalculatorGoldenCase::query()->create([
                'calculator_golden_case_set_id' => $locked->id,
                'name' => trim($validated['name']),
                'input_snapshot' => $inputs,
                'parameter_snapshot' => $validatedParameters,
                'expected_result_snapshot' => ['statutory_due_date' => $validated['expected']],
                'official_source_title' => trim($validated['sourceTitle']),
                'official_source_url' => $validated['sourceUrl'],
                'source_verified_on' => $validated['verifiedOn'],
                'prepared_by' => $actor->id,
            ]);
            $this->recordAudit->handle(
                action: 'calculator_golden_case.created',
                actor: $actor,
                auditable: $case,
                after: ['case_set_id' => $locked->id, 'calculator_key' => $locked->calculator_key],
            );

            return $case->refresh();
        }, 3);
    }

    private function authorize(User $actor, CalculatorGoldenCaseSet $set): void
    {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        if ($set->firm_id !== $firmId) {
            throw new AuthorizationException('The case set does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('create', Obligation::class);
    }
}
