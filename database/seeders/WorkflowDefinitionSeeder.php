<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Workflows\PublishCoreWorkflowVersion;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Database\Seeder;
use LogicException;

final class WorkflowDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Synthetic workflow data cannot be seeded in production.');
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
        app(PublishCoreWorkflowVersion::class)->handle(
            $administrator,
            'Standard compliance process',
        );
    }
}
