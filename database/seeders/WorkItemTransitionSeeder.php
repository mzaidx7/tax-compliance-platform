<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\WorkItemStatus;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemTransition;
use App\Tenancy\FirmContext;
use Illuminate\Database\Seeder;
use LogicException;

final class WorkItemTransitionSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Synthetic work transition data cannot be seeded in production.');
        }

        $administrator = User::query()->where('email', 'administrator@example.test')->firstOrFail();
        $workItems = WorkItem::withoutGlobalScopes()->with('firm')->get();

        foreach ($workItems as $workItem) {
            WorkItemTransition::factory()->createForWorkItem($workItem->firm, $workItem, [
                'transitioned_by' => $administrator->id,
                'reason' => 'Initial demo task status.',
            ]);
            app(FirmContext::class)->runForFirm(
                $workItem->firm,
                static fn (): bool => $workItem->update(['status' => WorkItemStatus::DocumentsRequested]),
            );
        }
    }
}
