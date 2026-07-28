<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WorkItemStatus;
use App\Models\Firm;
use App\Models\Obligation;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkItem;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<WorkItem>
 */
class WorkItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obligation_id' => null,
            'parent_work_item_id' => null,
            'primary_obligation_id' => null,
            'workflow_definition_id' => WorkflowDefinition::factory(),
            'status' => WorkItemStatus::NotStarted,
            'created_by' => User::factory(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function createForFirm(Firm $firm, Obligation $obligation, array $attributes = []): WorkItem
    {
        if ($obligation->firm_id !== $firm->id) {
            throw new LogicException('The work item obligation must belong to the selected firm.');
        }

        return app(FirmContext::class)->runForFirm($firm, function () use ($obligation, $attributes): WorkItem {
            $workItem = $this
                ->count(null)
                ->state([
                    'obligation_id' => $obligation->id,
                    'primary_obligation_id' => $obligation->id,
                ])
                ->create($attributes);

            if (! $workItem instanceof WorkItem) {
                throw new LogicException('The work item factory did not create one work item.');
            }

            return $workItem;
        });
    }
}
