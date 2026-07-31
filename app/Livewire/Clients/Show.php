<?php

declare(strict_types=1);

namespace App\Livewire\Clients;

use App\Models\AuditLog;
use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class Show extends Component
{
    #[Locked]
    public string $clientId;

    public string $tab = 'overview';

    public function mount(Client $client): void
    {
        Gate::authorize('view', $client);
        $this->clientId = $client->id;
    }

    public function updatedTab(): void
    {
        $this->validate([
            'tab' => ['required', Rule::in(['overview', 'vat', 'corporate-tax', 'documents', 'people', 'activity'])],
        ]);
    }

    #[Computed]
    public function client(): Client
    {
        $client = Client::query()->with([
            'contacts',
            'people.documents.documentTypeVersion',
            'documents' => static fn ($query) => $query->with(['documentTypeVersion', 'person'])->whereDoesntHave('successor'),
            'taxRegistrations.periods',
            'obligations' => static fn ($query) => $query->with('workItems')->orderByRaw('coalesce(effective_due_date, statutory_due_date)'),
        ])->findOrFail($this->clientId);
        Gate::authorize('view', $client);

        return $client;
    }

    /** @return Collection<int, AuditLog> */
    #[Computed]
    public function activity(): Collection
    {
        return AuditLog::query()
            ->where('auditable_type', $this->client()->getMorphClass())
            ->where('auditable_id', $this->clientId)
            ->latest('created_at')
            ->limit(100)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.clients.show')
            ->title($this->client()->internal_code.' client workspace');
    }
}
