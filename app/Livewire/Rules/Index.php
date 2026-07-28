<?php

declare(strict_types=1);

namespace App\Livewire\Rules;

use App\Actions\Rules\ApproveRuleVersion;
use App\Actions\Rules\CreateRuleTemplate;
use App\Actions\Rules\DraftRuleVersion;
use App\Actions\Rules\PublishRuleVersion;
use App\Actions\Rules\RetireRuleVersion;
use App\Actions\Rules\SubmitRuleVersionForReview;
use App\Contracts\ObligationCalculator;
use App\Enums\Feature;
use App\Models\Obligation;
use App\Models\ObligationRuleTemplate;
use App\Models\ObligationRuleVersion;
use App\Models\User;
use App\Support\CalculatorRegistry;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use JsonException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Rule governance')]
final class Index extends Component
{
    public string $templateKey = '';

    public string $templateName = '';

    public string $obligationType = '';

    public string $jurisdiction = 'United Arab Emirates';

    public string $authority = '';

    public string $ruleTemplateId = '';

    public string $effectiveFrom = '';

    public string $effectiveTo = '';

    public string $applicabilityCriteria = '';

    public string $calculatorKey = 'manual_date_passthrough';

    public string $parametersJson = '{}';

    public string $officialSourceTitle = '';

    public string $officialSourceUrl = '';

    public string $sourcePublishedOn = '';

    public string $changeSummary = '';

    public string $lifecycleVersionId = '';

    public string $lifecycleReason = '';

    public string $sourceLastVerifiedOn = '';

    public function mount(FeatureFlags $featureFlags, FirmContext $firmContext): void
    {
        abort_unless(
            $featureFlags->enabled(Feature::ComplianceOperations, $firmContext->firmId()),
            404,
        );
        Gate::authorize('viewAny', Obligation::class);
        $this->effectiveFrom = today()->toDateString();
        $this->sourceLastVerifiedOn = today()->toDateString();
    }

    public function createTemplate(CreateRuleTemplate $action): void
    {
        $template = $action->handle(
            $this->currentUser(),
            $this->templateKey,
            $this->templateName,
            $this->obligationType,
            $this->jurisdiction,
            $this->authority,
        );
        $this->reset('templateKey', 'templateName', 'obligationType', 'authority');
        $this->ruleTemplateId = $template->id;
        unset($this->templates);
        Flux::toast(variant: 'success', text: "Rule template {$template->name} created.");
    }

    public function draftVersion(DraftRuleVersion $action): void
    {
        $template = ObligationRuleTemplate::query()->findOrFail($this->ruleTemplateId);
        $version = $action->handle(
            $this->currentUser(),
            $template,
            $this->effectiveFrom,
            $this->optional($this->effectiveTo),
            $this->applicabilityCriteria,
            $this->calculatorKey,
            $this->parameters(),
            $this->officialSourceTitle,
            $this->officialSourceUrl,
            $this->optional($this->sourcePublishedOn),
            $this->changeSummary,
        );
        $this->reset(
            'effectiveTo',
            'applicabilityCriteria',
            'officialSourceTitle',
            'officialSourceUrl',
            'sourcePublishedOn',
            'changeSummary',
        );
        $this->parametersJson = '{}';
        $this->lifecycleVersionId = $version->id;
        unset($this->templates);
        Flux::toast(variant: 'success', text: "Rule version {$version->version} drafted.");
    }

    public function submitReview(SubmitRuleVersionForReview $action): void
    {
        $action->handle($this->currentUser(), $this->selectedVersion(), $this->lifecycleReason);
        $this->afterLifecycle('Rule submitted for review.');
    }

    public function approve(ApproveRuleVersion $action): void
    {
        $action->handle(
            $this->currentUser(),
            $this->selectedVersion(),
            $this->sourceLastVerifiedOn,
            $this->lifecycleReason,
        );
        $this->afterLifecycle('Rule approved.');
    }

    public function publish(PublishRuleVersion $action): void
    {
        $action->handle($this->currentUser(), $this->selectedVersion(), $this->lifecycleReason);
        $this->afterLifecycle('Rule published.');
    }

    public function retire(RetireRuleVersion $action): void
    {
        $action->handle($this->currentUser(), $this->selectedVersion(), $this->lifecycleReason);
        $this->afterLifecycle('Rule retired.');
    }

    /** @return Collection<int, ObligationRuleTemplate> */
    #[Computed]
    public function templates(): Collection
    {
        return ObligationRuleTemplate::query()
            ->with(['versions' => static fn ($query) => $query->with(['preparer', 'verifier', 'events.actor'])->orderByDesc('version')])
            ->orderBy('name')
            ->get();
    }

    /** @return list<ObligationCalculator> */
    #[Computed]
    public function calculators(): array
    {
        return app(CalculatorRegistry::class)->all();
    }

    public function render(): View
    {
        return view('livewire.rules.index');
    }

    /** @return array<string, mixed> */
    private function parameters(): array
    {
        try {
            $object = json_decode($this->parametersJson, false, 32, JSON_THROW_ON_ERROR);
            $parameters = json_decode($this->parametersJson, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['parametersJson' => 'Parameters must be a valid JSON object.']);
        }

        if (! is_object($object) || ! is_array($parameters)) {
            throw ValidationException::withMessages(['parametersJson' => 'Parameters must be a JSON object.']);
        }

        return $parameters;
    }

    private function selectedVersion(): ObligationRuleVersion
    {
        return ObligationRuleVersion::query()->findOrFail($this->lifecycleVersionId);
    }

    private function afterLifecycle(string $message): void
    {
        $this->reset('lifecycleReason');
        unset($this->templates);
        Flux::toast(variant: 'success', text: $message);
    }

    private function optional(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
