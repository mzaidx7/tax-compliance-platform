<?php

namespace Database\Seeders;

use App\Actions\Tenancy\CreateFirmMembership;
use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Synthetic development data cannot be seeded in production.');
        }

        $firm = Firm::factory()->create([
            'name' => 'Synthetic Compliance Practice',
            'slug' => 'synthetic-compliance-practice',
        ]);

        $administrator = User::factory()->create([
            'name' => 'Synthetic Firm Administrator',
            'email' => 'administrator@example.test',
        ]);

        app(CreateFirmMembership::class)->handle(
            $firm,
            $administrator,
            FirmRole::FirmAdministrator,
        );

        $this->call(ClientSeeder::class);
        $this->call(ObligationSeeder::class);
        $this->call(WorkflowDefinitionSeeder::class);
        $this->call(ChecklistSeeder::class);
        $this->call(WorkItemSeeder::class);
        $this->call(AssignmentHistorySeeder::class);
        $this->call(WorkItemTransitionSeeder::class);
    }
}
