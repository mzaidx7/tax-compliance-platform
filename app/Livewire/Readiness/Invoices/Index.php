<?php

declare(strict_types=1);

namespace App\Livewire\Readiness\Invoices;

use App\Actions\Readiness\AddInvoiceSampleField;
use App\Actions\Readiness\CreateInvoiceReadinessSample;
use App\Actions\Readiness\RecordInvoiceReadinessIssue;
use App\Actions\Readiness\ResolveInvoiceReadinessIssue;
use App\Enums\ClientStatus;
use App\Enums\Feature;
use App\Enums\InvoiceSampleFieldKey;
use App\Enums\ReadinessDataDomain;
use App\Enums\RuleVersionStatus;
use App\Models\Client;
use App\Models\DataQualityRuleVersion;
use App\Models\InvoiceReadinessIssue;
use App\Models\InvoiceReadinessSample;
use App\Models\InvoiceSampleField;
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

#[Title('Invoice data readiness')]
final class Index extends Component
{
    public string $clientId = '';

    public string $sampleReference = '';

    public string $sampleSourceReference = '';

    public string $sampleId = '';

    public string $fieldKey = 'invoice_number';

    public string $fieldValue = '';

    public string $fieldSourceReference = '';

    public string $issueSampleId = '';

    public string $issueFieldId = '';

    public string $issueRuleId = '';

    public string $issueEvidence = '';

    public string $resolutionIssueId = '';

    public string $resolutionOutcome = '';

    public string $resolutionReason = '';

    public function mount(FeatureFlags $flags, FirmContext $context): void
    {
        abort_unless($flags->enabled(Feature::EInvoicingReadiness, $context->firmId()), 404);
        Gate::authorize('viewAny', InvoiceReadinessSample::class);
    }

    public function createSample(CreateInvoiceReadinessSample $action): void
    {
        $sample = $action->handle($this->user(), Client::query()->findOrFail($this->clientId), $this->sampleReference, $this->sampleSourceReference);
        $this->sampleId = $sample->id;
        $this->reset('sampleReference', 'sampleSourceReference');
        unset($this->samples);
        Flux::toast(variant: 'success', text: __('Synthetic invoice sample retained.'));
    }

    public function addField(AddInvoiceSampleField $action): void
    {
        $action->handle(
            $this->user(), InvoiceReadinessSample::query()->findOrFail($this->sampleId),
            $this->fieldKey, $this->fieldValue, $this->fieldSourceReference,
        );
        $this->reset('fieldValue', 'fieldSourceReference');
        unset($this->samples);
        Flux::toast(variant: 'success', text: __('Supplied invoice field retained.'));
    }

    public function recordIssue(RecordInvoiceReadinessIssue $action): void
    {
        $issue = $action->handle(
            $this->user(), InvoiceReadinessSample::query()->findOrFail($this->issueSampleId),
            $this->issueFieldId === '' ? null : InvoiceSampleField::query()->findOrFail($this->issueFieldId),
            DataQualityRuleVersion::query()->findOrFail($this->issueRuleId), $this->issueEvidence,
        );
        $this->resolutionIssueId = $issue->id;
        $this->reset('issueEvidence');
        unset($this->samples);
        Flux::toast(variant: 'success', text: __('Explainable invoice issue retained.'));
    }

    public function resolveIssue(ResolveInvoiceReadinessIssue $action): void
    {
        $action->handle(
            $this->user(), InvoiceReadinessIssue::query()->findOrFail($this->resolutionIssueId),
            $this->resolutionOutcome, $this->resolutionReason,
        );
        $this->reset('resolutionOutcome', 'resolutionReason');
        unset($this->samples);
        Flux::toast(variant: 'success', text: __('Invoice issue decision retained.'));
    }

    /** @return Collection<int, Client> */
    #[Computed]
    public function clients(): Collection
    {
        return Client::query()->where('status', ClientStatus::Active)->orderBy('legal_name')->get();
    }

    /** @return Collection<int, InvoiceReadinessSample> */
    #[Computed]
    public function samples(): Collection
    {
        return InvoiceReadinessSample::query()
            ->with([
                'client',
                'fields' => static fn ($query) => $query->orderBy('field_key'),
                'issues' => static fn ($query) => $query->with(['ruleVersion.definition', 'field', 'resolution'])->orderByDesc('recorded_at'),
            ])
            ->orderBy('sample_reference')->get();
    }

    /** @return Collection<int, DataQualityRuleVersion> */
    #[Computed]
    public function publishedInvoiceRules(): Collection
    {
        return DataQualityRuleVersion::query()
            ->with('definition')
            ->where('status', RuleVersionStatus::Published)
            ->whereHas('definition', fn ($query) => $query->where('data_domain', ReadinessDataDomain::InvoiceTransaction))
            ->orderBy('data_quality_rule_definition_id')->get();
    }

    /** @return list<InvoiceSampleFieldKey> */
    public function fieldKeys(): array
    {
        return InvoiceSampleFieldKey::cases();
    }

    public function render(): View
    {
        return view('livewire.readiness.invoices.index');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
