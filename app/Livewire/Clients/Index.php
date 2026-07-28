<?php

declare(strict_types=1);

namespace App\Livewire\Clients;

use App\Actions\Clients\AddClientServiceEnrollment;
use App\Actions\Clients\AddTaxPeriod;
use App\Actions\Clients\AddTaxRegistration;
use App\Actions\Clients\CreateClient;
use App\Enums\ClientService;
use App\Enums\Feature;
use App\Enums\FirmMembershipStatus;
use App\Enums\TaxRegistrationStatus;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\FirmMembership;
use App\Models\TaxRegistration;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clients')]
final class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $internalCode = '';

    public string $legalName = '';

    public string $tradeName = '';

    public string $entityType = '';

    public bool $showProfileModal = false;

    public ?string $selectedClientId = null;

    public string $selectedClientLabel = '';

    public string $service = ClientService::VatCompliance->value;

    public string $serviceStartsOn = '';

    public string $serviceEndsOn = '';

    public string $responsibleMembershipId = '';

    public string $taxType = TaxType::Vat->value;

    public string $registrationNumber = '';

    public string $registrationStatus = TaxRegistrationStatus::Active->value;

    public string $registrationEffectiveFrom = '';

    public string $registrationEffectiveTo = '';

    public string $periodRegistrationId = '';

    public string $periodLabel = '';

    public string $periodStartsOn = '';

    public string $periodEndsOn = '';

    public function mount(FeatureFlags $featureFlags, FirmContext $firmContext): void
    {
        abort_unless(
            $featureFlags->enabled(Feature::ClientMaster, $firmContext->firmId()),
            404,
        );

        Gate::authorize('viewAny', Client::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function createClient(CreateClient $createClient): void
    {
        $client = $createClient->handle($this->currentUser(), [
            'internalCode' => $this->internalCode,
            'legalName' => $this->legalName,
            'tradeName' => $this->tradeName,
            'entityType' => $this->entityType,
        ]);

        $this->reset('internalCode', 'legalName', 'tradeName', 'entityType');
        $this->resetErrorBag();
        $this->resetPage();
        unset($this->clients);

        Flux::toast(
            variant: 'success',
            text: "Client {$client->internal_code} created.",
        );
    }

    public function openProfile(string $clientId): void
    {
        $client = Client::query()->findOrFail($clientId);
        Gate::authorize('update', $client);
        $this->resetProfileForms();
        $this->selectedClientId = $client->id;
        $this->selectedClientLabel = "{$client->internal_code} · {$client->legal_name}";
        $this->serviceStartsOn = today()->toDateString();
        $this->registrationEffectiveFrom = today()->toDateString();
        $this->showProfileModal = true;
        unset($this->selectedClient);
    }

    public function addService(AddClientServiceEnrollment $action): void
    {
        $client = $this->selectedClientOrFail();
        $action->handle(
            $this->currentUser(),
            $client,
            ClientService::from($this->service),
            $this->serviceStartsOn,
            $this->optional($this->serviceEndsOn),
            $this->responsibleMembershipId,
        );
        $this->reset('serviceEndsOn');
        unset($this->selectedClient, $this->clients);
    }

    public function addRegistration(AddTaxRegistration $action): void
    {
        $client = $this->selectedClientOrFail();
        $registration = $action->handle(
            $this->currentUser(),
            $client,
            TaxType::from($this->taxType),
            $this->registrationNumber,
            TaxRegistrationStatus::from($this->registrationStatus),
            $this->optional($this->registrationEffectiveFrom),
            $this->optional($this->registrationEffectiveTo),
        );
        $this->periodRegistrationId = $registration->id;
        $this->reset('registrationNumber', 'registrationEffectiveTo');
        unset($this->selectedClient, $this->clients);
    }

    public function addPeriod(AddTaxPeriod $action): void
    {
        $registration = TaxRegistration::query()->findOrFail($this->periodRegistrationId);
        $action->handle(
            $this->currentUser(),
            $registration,
            $this->periodLabel,
            $this->periodStartsOn,
            $this->periodEndsOn,
        );
        $this->reset('periodLabel', 'periodStartsOn', 'periodEndsOn');
        unset($this->selectedClient);
    }

    public function closeProfile(): void
    {
        $this->showProfileModal = false;
        $this->resetProfileForms();
    }

    /**
     * @return LengthAwarePaginator<int, Client>
     */
    #[Computed]
    public function clients(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Client::query()
            ->withCount(['serviceEnrollments', 'taxRegistrations'])
            ->when(
                $search !== '',
                static function (Builder $query) use ($search): void {
                    $query->where(static function (Builder $query) use ($search): void {
                        $query
                            ->where('internal_code', 'like', "%{$search}%")
                            ->orWhere('legal_name', 'like', "%{$search}%")
                            ->orWhere('trade_name', 'like', "%{$search}%");
                    });
                },
            )
            ->orderBy('legal_name')
            ->orderBy('id')
            ->paginate(25);
    }

    #[Computed]
    public function selectedClient(): ?Client
    {
        if ($this->selectedClientId === null) {
            return null;
        }

        return Client::query()
            ->with([
                'serviceEnrollments.responsibleMembership.user',
                'taxRegistrations.periods',
            ])
            ->find($this->selectedClientId);
    }

    /** @return list<ClientService> */
    #[Computed]
    public function services(): array
    {
        return ClientService::cases();
    }

    /** @return list<TaxType> */
    #[Computed]
    public function taxTypes(): array
    {
        return TaxType::cases();
    }

    /** @return list<TaxRegistrationStatus> */
    #[Computed]
    public function registrationStatuses(): array
    {
        return TaxRegistrationStatus::cases();
    }

    /** @return Collection<int, FirmMembership> */
    #[Computed]
    public function responsibleMembers(): Collection
    {
        return FirmMembership::query()
            ->with('user')
            ->where('status', FirmMembershipStatus::Active)
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function currentFirmName(): string
    {
        return app(FirmContext::class)->firm()->name;
    }

    public function render(): View
    {
        return view('livewire.clients.index');
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function selectedClientOrFail(): Client
    {
        return Client::query()->findOrFail($this->selectedClientId);
    }

    private function optional(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function resetProfileForms(): void
    {
        $this->reset(
            'selectedClientId',
            'selectedClientLabel',
            'serviceEndsOn',
            'responsibleMembershipId',
            'registrationNumber',
            'registrationEffectiveTo',
            'periodRegistrationId',
            'periodLabel',
            'periodStartsOn',
            'periodEndsOn',
        );
        $this->service = ClientService::VatCompliance->value;
        $this->taxType = TaxType::Vat->value;
        $this->registrationStatus = TaxRegistrationStatus::Active->value;
        $this->resetErrorBag();
        unset($this->selectedClient);
    }
}
