<?php

declare(strict_types=1);

namespace App\Livewire\Readiness\Parties;

use App\Actions\Readiness\AddInitialPartyField;
use App\Actions\Readiness\CreatePartyRecord;
use App\Actions\Readiness\DecidePartyFieldCorrection;
use App\Actions\Readiness\ProposePartyFieldCorrection;
use App\Actions\Readiness\RecordPartyIssue;
use App\Actions\Readiness\ResolvePartyIssue;
use App\Enums\ClientStatus;
use App\Enums\Feature;
use App\Enums\PartyFieldKey;
use App\Enums\PartyFieldVerificationState;
use App\Enums\ReadinessDataDomain;
use App\Enums\RuleVersionStatus;
use App\Models\Client;
use App\Models\DataQualityRuleVersion;
use App\Models\PartyCorrectionProposal;
use App\Models\PartyFieldVersion;
use App\Models\PartyIssue;
use App\Models\PartyRecord;
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

#[Title('Readiness party master')]
final class Index extends Component
{
    public string $clientId = '';

    public string $reference = '';

    public bool $isCustomer = true;

    public bool $isSupplier = false;

    public bool $isActive = true;

    public string $partyId = '';

    public string $fieldKey = 'legal_name';

    public string $fieldValue = '';

    public string $verificationState = 'unverified';

    public string $sourceReference = '';

    public string $currentFieldVersionId = '';

    public string $proposedValue = '';

    public string $evidenceNote = '';

    public string $decisionProposalId = '';

    public string $decision = '';

    public string $decisionReason = '';

    public string $issuePartyId = '';

    public string $issueFieldVersionId = '';

    public string $issueRuleVersionId = '';

    public string $issueEvidenceNote = '';

    public string $resolutionIssueId = '';

    public string $resolutionOutcome = '';

    public string $resolutionReason = '';

    public function mount(FeatureFlags $flags, FirmContext $context): void
    {
        abort_unless($flags->enabled(Feature::EInvoicingReadiness, $context->firmId()), 404);
        Gate::authorize('viewAny', PartyRecord::class);
    }

    public function createParty(CreatePartyRecord $action): void
    {
        $party = $action->handle(
            $this->user(), Client::query()->findOrFail($this->clientId), $this->reference,
            $this->isCustomer, $this->isSupplier, $this->isActive,
        );
        $this->partyId = $party->id;
        $this->reset('reference');
        unset($this->parties);
        Flux::toast(variant: 'success', text: __('Party identity recorded.'));
    }

    public function addField(AddInitialPartyField $action): void
    {
        $field = $action->handle(
            $this->user(), PartyRecord::query()->findOrFail($this->partyId), $this->fieldKey,
            $this->fieldValue, $this->verificationState, $this->sourceReference,
        );
        $this->currentFieldVersionId = $field->id;
        $this->reset('fieldValue', 'sourceReference');
        unset($this->parties);
        Flux::toast(variant: 'success', text: __('Initial field provenance recorded.'));
    }

    public function proposeCorrection(ProposePartyFieldCorrection $action): void
    {
        $field = PartyFieldVersion::query()->findOrFail($this->currentFieldVersionId);
        $proposal = $action->handle(
            $this->user(), PartyRecord::query()->findOrFail($field->party_record_id),
            $field, $this->proposedValue, $this->evidenceNote,
        );
        $this->decisionProposalId = $proposal->id;
        $this->reset('proposedValue', 'evidenceNote');
        unset($this->parties);
        Flux::toast(variant: 'success', text: __('Correction proposal recorded.'));
    }

    public function decideCorrection(DecidePartyFieldCorrection $action): void
    {
        $action->handle(
            $this->user(), PartyCorrectionProposal::query()->findOrFail($this->decisionProposalId),
            $this->decision, $this->decisionReason,
        );
        $this->reset('decision', 'decisionReason');
        unset($this->parties);
        Flux::toast(variant: 'success', text: __('Correction decision retained.'));
    }

    public function recordIssue(RecordPartyIssue $action): void
    {
        $issue = $action->handle(
            $this->user(),
            PartyRecord::query()->findOrFail($this->issuePartyId),
            $this->issueFieldVersionId === '' ? null : PartyFieldVersion::query()->findOrFail($this->issueFieldVersionId),
            DataQualityRuleVersion::query()->findOrFail($this->issueRuleVersionId),
            $this->issueEvidenceNote,
        );
        $this->resolutionIssueId = $issue->id;
        $this->reset('issueEvidenceNote');
        unset($this->parties);
        Flux::toast(variant: 'success', text: __('Explainable party issue recorded.'));
    }

    public function resolveIssue(ResolvePartyIssue $action): void
    {
        $action->handle(
            $this->user(), PartyIssue::query()->findOrFail($this->resolutionIssueId),
            $this->resolutionOutcome, $this->resolutionReason,
        );
        $this->reset('resolutionOutcome', 'resolutionReason');
        unset($this->parties);
        Flux::toast(variant: 'success', text: __('Party issue decision retained.'));
    }

    /** @return Collection<int, Client> */
    #[Computed]
    public function clients(): Collection
    {
        return Client::query()->where('status', ClientStatus::Active)->orderBy('legal_name')->get();
    }

    /** @return Collection<int, PartyRecord> */
    #[Computed]
    public function parties(): Collection
    {
        return PartyRecord::query()
            ->with([
                'client',
                'fieldVersions' => static fn ($query) => $query->orderBy('recorded_at')->orderBy('id'),
                'correctionProposals' => static fn ($query) => $query->with(['currentFieldVersion', 'decision'])->orderByDesc('proposed_at'),
                'issues' => static fn ($query) => $query->with(['ruleVersion.definition', 'resolution'])->orderByDesc('recorded_at'),
            ])
            ->orderBy('reference')->get();
    }

    /** @return Collection<int, DataQualityRuleVersion> */
    #[Computed]
    public function publishedPartyRules(): Collection
    {
        return DataQualityRuleVersion::query()
            ->with('definition')
            ->where('status', RuleVersionStatus::Published)
            ->whereHas('definition', fn ($query) => $query->where('data_domain', ReadinessDataDomain::PartyMaster))
            ->orderBy('data_quality_rule_definition_id')
            ->get();
    }

    /** @return list<PartyFieldKey> */
    public function fieldKeys(): array
    {
        return PartyFieldKey::cases();
    }

    /** @return list<PartyFieldVerificationState> */
    public function verificationStates(): array
    {
        return PartyFieldVerificationState::cases();
    }

    public function render(): View
    {
        return view('livewire.readiness.parties.index');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
