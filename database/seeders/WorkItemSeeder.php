<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChecklistTemplate;
use App\Models\ChecklistVersion;
use App\Models\Obligation;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkItem;
use App\Models\WorkItemChecklist;
use App\Tenancy\FirmContext;
use Illuminate\Database\Seeder;
use LogicException;

final class WorkItemSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Synthetic work item data cannot be seeded in production.');
        }

        $administrator = User::query()->where('email', 'administrator@example.test')->firstOrFail();
        $obligations = Obligation::withoutGlobalScopes()->with('firm')->get();

        foreach ($obligations as $obligation) {
            $workflowDefinition = WorkflowDefinition::withoutGlobalScopes()
                ->where('firm_id', $obligation->firm_id)
                ->where('definition_key', WorkflowDefinition::CORE_KEY)
                ->where('status', 'published')
                ->latest('version')
                ->firstOrFail();
            $workItem = WorkItem::factory()->createForFirm($obligation->firm, $obligation, [
                'created_by' => $administrator->id,
                'workflow_definition_id' => $workflowDefinition->id,
            ]);
            $version = ChecklistVersion::withoutGlobalScopes()
                ->where('firm_id', $obligation->firm_id)
                ->where('status', 'published')
                ->whereHas('template', static fn ($query) => $query->where('template_key', ChecklistTemplate::CORE_KEY))
                ->latest('version')
                ->firstOrFail();
            app(FirmContext::class)->runForFirm(
                $obligation->firm,
                static fn (): WorkItemChecklist => WorkItemChecklist::query()->create([
                    'work_item_id' => $workItem->id,
                    'checklist_version_id' => $version->id,
                    'applied_by' => $administrator->id,
                    'applied_at' => now('UTC'),
                ]),
            );
        }
    }
}
