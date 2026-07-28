<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Settings\SetFeatureFlagOverride;
use App\Enums\Feature;
use App\Models\FeatureFlagOverride;
use App\Models\User;
use App\Support\FeatureFlags as FeatureFlagReader;
use App\Tenancy\FirmContext;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Feature administration')]
final class FeatureFlags extends Component
{
    public bool $showModal = false;

    #[Locked]
    public string $editingFeature = '';

    public bool $desiredEnabled = false;

    public string $reason = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', FeatureFlagOverride::class);
    }

    /**
     * @return list<array{value: string, label: string, description: string, effective: bool, overridden: bool}>
     */
    #[Computed]
    public function features(): array
    {
        $reader = app(FeatureFlagReader::class);
        $firmId = app(FirmContext::class)->firmId();
        $overriddenFeatures = FeatureFlagOverride::query()->pluck('feature')
            ->map(static fn (Feature $feature): string => $feature->value)
            ->all();

        return array_map(
            static fn (Feature $feature): array => [
                'value' => $feature->value,
                'label' => $feature->label(),
                'description' => $feature->description(),
                'effective' => $reader->enabled($feature, $firmId),
                'overridden' => in_array($feature->value, $overriddenFeatures, true),
            ],
            Feature::cases(),
        );
    }

    #[Computed]
    public function editingLabel(): string
    {
        return $this->editingFeature === '' ? '' : Feature::from($this->editingFeature)->label();
    }

    public function openOverride(string $feature): void
    {
        $resolved = Feature::from($feature);
        Gate::authorize('update', FeatureFlagOverride::class);

        $this->resetErrorBag();
        $this->editingFeature = $resolved->value;
        $this->desiredEnabled = app(FeatureFlagReader::class)
            ->enabled($resolved, app(FirmContext::class)->firmId());
        $this->reason = '';
        $this->showModal = true;
    }

    public function saveOverride(SetFeatureFlagOverride $setFeatureFlagOverride): void
    {
        $this->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $setFeatureFlagOverride->handle(
            $this->currentUser(),
            Feature::from($this->editingFeature),
            $this->desiredEnabled,
            $this->reason,
        );

        $this->closeModal();
        unset($this->features);
        Flux::toast(variant: 'success', text: 'Feature availability recorded with audit evidence.');
    }

    public function closeModal(): void
    {
        $this->reset('showModal', 'editingFeature', 'desiredEnabled', 'reason');
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.settings.feature-flags');
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
