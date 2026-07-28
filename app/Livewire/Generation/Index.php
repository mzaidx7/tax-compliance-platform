<?php

declare(strict_types=1);

namespace App\Livewire\Generation;

use App\Actions\Generation\ApproveRuleChange;
use App\Actions\Generation\CommitGeneratedObligation;
use App\Actions\Generation\PreviewGeneratedObligation;
use App\Actions\Generation\ProposeRuleChange;
use App\Enums\Feature;
use App\Enums\ObligationOrigin;
use App\Enums\ObligationStatus;
use App\Enums\RuleVersionStatus;
use App\Models\Client;
use App\Models\ClientServiceEnrollment;
use App\Models\Obligation;
use App\Models\ObligationGenerationRun;
use App\Models\ObligationRuleVersion;
use App\Models\RuleChangeProposal;
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

#[Title('Create deadlines')]
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

    public string $proposalOriginalObligationId = '';

    public string $proposalRuleVersionId = '';

    public string $proposalStatutoryDueDate = '';

    public string $proposalInternalTargetDate = '';

    public string $proposalReason = '';

    public ?string $activeProposalId = null;

    public string $approvalReason = '';

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

    public function proposeRuleChange(ProposeRuleChange $action): void
    {
        $proposal = $action->handle(
            $this->currentUser(),
            Obligation::query()->findOrFail($this->proposalOriginalObligationId),
            ObligationRuleVersion::query()->findOrFail($this->proposalRuleVersionId),
            [
                'statutoryDueDate' => $this->proposalStatutoryDueDate,
                'internalTargetDate' => $this->optional($this->proposalInternalTargetDate),
                'reason' => $this->proposalReason,
            ],
        );
        $this->activeProposalId = $proposal->id;
        $this->approvalReason = '';
        unset($this->activeProposal);
        Flux::toast(variant: 'success', text: __('Changed-rule proposal recorded.'));
    }

    public function approveRuleChange(ApproveRuleChange $action): void
    {
        $proposal = RuleChangeProposal::query()->findOrFail($this->activeProposalId);
        $action->handle($this->currentUser(), $proposal, $this->approvalReason);
        unset($this->activeProposal, $this->issuedGovernedObligations);
        Flux::toast(variant: 'success', text: __('Changed-rule proposal approved and replacement issued.'));
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

    /** @return Collection<int, Obligation> */
    #[Computed]
    public function issuedGovernedObligations(): Collection
    {
        return Obligation::query()
            ->with(['client', 'ruleVersion.template'])
            ->where('origin', ObligationOrigin::GovernedRule)
            ->where('status', ObligationStatus::Open)
            ->orderByRaw('coalesce(effective_due_date, statutory_due_date)')
            ->get();
    }

    #[Computed]
    public function activeProposal(): ?RuleChangeProposal
    {
        if ($this->activeProposalId === null) {
            return null;
        }

        return RuleChangeProposal::query()
            ->with(['originalObligation.client', 'proposedRuleVersion.template', 'previewRun', 'decision.replacementObligation'])
            ->find($this->activeProposalId);
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
