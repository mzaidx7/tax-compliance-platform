<?php

declare(strict_types=1);

namespace App\Livewire\Generation;

use App\Actions\Generation\CommitGeneratedObligation;
use App\Actions\Generation\PreviewGeneratedObligation;
use App\Enums\Feature;
use App\Enums\RuleVersionStatus;
use App\Models\Client;
use App\Models\ClientServiceEnrollment;
use App\Models\Obligation;
use App\Models\ObligationGenerationRun;
use App\Models\ObligationRuleVersion;
use App\Models\TaxPeriod;
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

#[Title('Obligation generation')]
final class Index extends Component
{
    public string $clientId = '';

    public string $serviceEnrollmentId = '';

    public string $taxPeriodId = '';

    public string $ruleVersionId = '';

    public string $applicabilityDate = '';

    public string $periodLabel = '';

    public string $statutoryDueDate = '';

    public string $internalTargetDate = '';

    public ?string $previewRunId = null;

    public ?string $committedObligationId = null;

    public function mount(FeatureFlags $featureFlags, FirmContext $firmContext): void
    {
        abort_unless(
            $featureFlags->enabled(Feature::ComplianceOperations, $firmContext->firmId()),
            404,
        );
        Gate::authorize('viewAny', Obligation::class);
        $this->applicabilityDate = today()->toDateString();
    }

    public function preview(PreviewGeneratedObligation $action): void
    {
        $run = $action->handle(
            $this->currentUser(),
            Client::query()->findOrFail($this->clientId),
            ClientServiceEnrollment::query()->findOrFail($this->serviceEnrollmentId),
            $this->taxPeriodId === '' ? null : TaxPeriod::query()->findOrFail($this->taxPeriodId),
            ObligationRuleVersion::query()->findOrFail($this->ruleVersionId),
            [
                'applicabilityDate' => $this->applicabilityDate,
                'periodLabel' => $this->periodLabel,
                'statutoryDueDate' => $this->statutoryDueDate,
                'internalTargetDate' => $this->optional($this->internalTargetDate),
            ],
        );
        $this->previewRunId = $run->id;
        $this->committedObligationId = null;
        unset($this->previewRun);
        Flux::toast(variant: 'success', text: 'Generation preview recorded.');
    }

    public function commit(CommitGeneratedObligation $action): void
    {
        $preview = ObligationGenerationRun::query()->findOrFail($this->previewRunId);
        $obligation = $action->handle($this->currentUser(), $preview);
        $this->committedObligationId = $obligation->id;
        Flux::toast(variant: 'success', text: "Obligation {$obligation->id} committed.");
    }

    /** @return Collection<int, Client> */
    #[Computed]
    public function clients(): Collection
    {
        return Client::query()->orderBy('legal_name')->get();
    }

    /** @return Collection<int, ClientServiceEnrollment> */
    #[Computed]
    public function serviceEnrollments(): Collection
    {
        return ClientServiceEnrollment::query()->with('client')->orderBy('starts_on')->get();
    }

    /** @return Collection<int, TaxPeriod> */
    #[Computed]
    public function taxPeriods(): Collection
    {
        return TaxPeriod::query()->with('registration.client')->orderBy('starts_on')->get();
    }

    /** @return Collection<int, ObligationRuleVersion> */
    #[Computed]
    public function publishedRules(): Collection
    {
        return ObligationRuleVersion::query()
            ->with('template')
            ->where('status', RuleVersionStatus::Published)
            ->orderBy('effective_from')
            ->get();
    }

    #[Computed]
    public function previewRun(): ?ObligationGenerationRun
    {
        if ($this->previewRunId === null) {
            return null;
        }

        return ObligationGenerationRun::query()
            ->with(['client', 'serviceEnrollment', 'taxPeriod', 'ruleVersion.template'])
            ->find($this->previewRunId);
    }

    public function render(): View
    {
        return view('livewire.generation.index');
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
