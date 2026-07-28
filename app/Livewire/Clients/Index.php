<?php

declare(strict_types=1);

namespace App\Livewire\Clients;

use App\Actions\Clients\CreateClient;
use App\Enums\Feature;
use App\Models\Client;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * @return LengthAwarePaginator<int, Client>
     */
    #[Computed]
    public function clients(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Client::query()
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
}
