<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\ReadinessDataDomain;
use App\Models\DataQualityRuleDefinition;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class CreateDataQualityRuleDefinition
{
    public function __construct(private FirmContext $firmContext, private FeatureFlags $featureFlags, private RecordAudit $recordAudit) {}

    public function handle(User $actor, mixed $key, mixed $name, mixed $domain, mixed $fieldOrScenario): DataQualityRuleDefinition
    {
        $firmId = $this->firmContext->firmId();
        if (! $this->featureFlags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        Gate::forUser($actor)->authorize('create', DataQualityRuleDefinition::class);
        /** @var array{key: string, name: string, domain: string, field: string} $validated */
        $validated = Validator::make(['key' => $key, 'name' => $name, 'domain' => $domain, 'field' => $fieldOrScenario], [
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'domain' => ['required', Rule::enum(ReadinessDataDomain::class)],
            'field' => ['required', 'string', 'max:150'],
        ])->validate();

        return DB::transaction(function () use ($actor, $validated): DataQualityRuleDefinition {
            $definition = DataQualityRuleDefinition::query()->create([
                'key' => $validated['key'], 'name' => trim($validated['name']),
                'data_domain' => $validated['domain'], 'field_or_scenario' => trim($validated['field']),
                'created_by' => $actor->id,
            ]);
            $this->recordAudit->handle('data_quality_rule.created', $actor, $definition, [], [
                'key' => $definition->key, 'data_domain' => $definition->data_domain->value,
                'field_or_scenario' => $definition->field_or_scenario,
            ]);

            return $definition->refresh();
        }, 3);
    }
}
