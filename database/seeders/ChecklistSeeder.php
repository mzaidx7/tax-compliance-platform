<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Workflows\PublishChecklistVersion;
use App\Models\ChecklistTemplate;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Database\Seeder;
use LogicException;

final class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Synthetic checklist data cannot be seeded in production.');
        }

        $administrator = User::query()->where('email', 'administrator@example.test')->firstOrFail();
        $membership = FirmMembership::withoutGlobalScopes()
            ->with('firm')
            ->where('user_id', $administrator->id)
            ->firstOrFail();
        config([
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
        app(FirmContext::class)->activateMembership($membership);
        app(PublishChecklistVersion::class)->handle(
            $administrator,
            ChecklistTemplate::CORE_KEY,
            'Synthetic core compliance checklist',
            [
                ['key' => 'review-source-records', 'label' => 'Review the synthetic source records'],
                ['key' => 'record-preparation-note', 'label' => 'Record a synthetic preparation note'],
                ['key' => 'confirm-review-package', 'label' => 'Confirm the synthetic review package'],
            ],
        );
    }
}
