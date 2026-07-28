<?php

declare(strict_types=1);

namespace App\Livewire\Readiness\Rules;

use App\Actions\Readiness\CreateDataQualityRuleDefinition;
use App\Actions\Readiness\DraftDataQualityRuleVersion;
use App\Actions\Readiness\TransitionDataQualityRuleVersion;
use App\Enums\DataQualityBehavior;
use App\Enums\DataQualitySeverity;
use App\Enums\Feature;
use App\Enums\ReadinessDataDomain;
use App\Models\DataQualityRuleDefinition;
use App\Models\DataQualityRuleVersion;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Readiness rule governance')]
final class Index extends Component
{
    public string $definitionKey = '';

    public string $definitionName = '';

    public string $dataDomain = 'party_master';

    public string $fieldOrScenario = '';

    public string $definitionId = '';

    public string $applicability = '';

    public string $severity = 'medium';

    public string $behavior = 'warning';

    public string $explanation = '';

    public string $remediation = '';

    public string $sourceKind = 'internal';

    public string $sourceTitle = '';

    public string $sourceUrl = '';

    public string $formulaEffect = 'No readiness score effect is approved.';

    public string $changeSummary = '';

    public string $lifecycleVersionId = '';

    public string $targetStatus = '';

    public string $lifecycleReason = '';

    public string $sourceLastVerifiedOn = '';

    public function mount(FeatureFlags $flags, FirmContext $context): void
    {
        abort_unless($flags->enabled(Feature::EInvoicingReadiness, $context->firmId()), 404);
        Gate::authorize('viewAny', DataQualityRuleDefinition::class);
        $this->sourceLastVerifiedOn = today()->toDateString();
    }

    public function createDefinition(CreateDataQualityRuleDefinition $action): void
    {
        $definition = $action->handle($this->user(), $this->definitionKey, $this->definitionName, $this->dataDomain, $this->fieldOrScenario);
        $this->definitionId = $definition->id;
        $this->reset('definitionKey', 'definitionName', 'fieldOrScenario');
        unset($this->definitions);
        Flux::toast(variant: 'success', text: __('Readiness rule identity created.'));
    }

    public function draftVersion(DraftDataQualityRuleVersion $action): void
    {
        $version = $action->handle($this->user(), DataQualityRuleDefinition::query()->findOrFail($this->definitionId), [
            'applicability' => $this->applicability, 'severity' => $this->severity, 'behavior' => $this->behavior,
            'explanation' => $this->explanation, 'remediation' => $this->remediation,
            'sourceKind' => $this->sourceKind, 'sourceTitle' => $this->sourceTitle,
            'sourceUrl' => trim($this->sourceUrl) === '' ? null : $this->sourceUrl,
            'formulaEffect' => $this->formulaEffect, 'changeSummary' => $this->changeSummary,
        ]);
        $this->lifecycleVersionId = $version->id;
        $this->reset('applicability', 'explanation', 'remediation', 'sourceTitle', 'sourceUrl', 'changeSummary');
        unset($this->definitions);
        Flux::toast(variant: 'success', text: __('Immutable readiness rule draft created.'));
    }

    public function transitionRule(TransitionDataQualityRuleVersion $action): void
    {
        $action->handle(
            $this->user(),
            DataQualityRuleVersion::query()->findOrFail($this->lifecycleVersionId),
            $this->targetStatus,
            $this->lifecycleReason,
            $this->targetStatus === 'approved' ? $this->sourceLastVerifiedOn : null,
        );
        $this->reset('targetStatus', 'lifecycleReason');
        unset($this->definitions);
        Flux::toast(variant: 'success', text: __('Readiness rule lifecycle updated.'));
    }

    /** @return Collection<int, DataQualityRuleDefinition> */
    #[Computed]
    public function definitions(): Collection
    {
        return DataQualityRuleDefinition::query()
            ->with(['versions' => static fn ($query) => $query->with('events')->orderByDesc('version')])
            ->orderBy('data_domain')->orderBy('name')->get();
    }

    /** @return list<ReadinessDataDomain> */
    public function domains(): array
    {
        return ReadinessDataDomain::cases();
    }

    /** @return list<DataQualitySeverity> */
    public function severities(): array
    {
        return DataQualitySeverity::cases();
    }

    /** @return list<DataQualityBehavior> */
    public function behaviors(): array
    {
        return DataQualityBehavior::cases();
    }

    public function render(): View
    {
        return view('livewire.readiness.rules.index');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
