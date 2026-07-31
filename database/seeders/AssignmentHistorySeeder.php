<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AssignmentRole;
use App\Models\AssignmentHistory;
use App\Models\FirmMembership;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Seeder;
use LogicException;

final class AssignmentHistorySeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Synthetic assignment data cannot be seeded in production.');
        }

        $administrator = User::query()->where('email', 'administrator@example.test')->firstOrFail();
        $workItems = WorkItem::withoutGlobalScopes()->with('firm')->get();

        foreach ($workItems as $workItem) {
            $membership = FirmMembership::withoutGlobalScopes()
                ->where('firm_id', $workItem->firm_id)
                ->where('user_id', $administrator->id)
                ->firstOrFail();

            foreach (AssignmentRole::cases() as $role) {
                AssignmentHistory::factory()->createForWorkItem($workItem->firm, $workItem, $membership, [
                    'assignment_role' => $role,
                    'assigned_by' => $administrator->id,
                    'reason' => 'Initial demo assignment.',
                ]);
            }
        }
    }
}
