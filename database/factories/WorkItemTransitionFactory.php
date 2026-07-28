<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WorkItemStatus;
use App\Models\Firm;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemTransition;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<WorkItemTransition>
 */
class WorkItemTransitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_item_id' => null,
            'from_status' => WorkItemStatus::NotStarted,
            'to_status' => WorkItemStatus::DocumentsRequested,
            'transitioned_by' => User::factory(),
            'reason' => 'Synthetic work transition fixture.',
            'transitioned_at' => now(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function createForWorkItem(Firm $firm, WorkItem $workItem, array $attributes = []): WorkItemTransition
    {
        if ($workItem->firm_id !== $firm->id) {
            throw new LogicException('The work transition must use the work item firm.');
        }

        return app(FirmContext::class)->runForFirm(
            $firm,
            function () use ($workItem, $attributes): WorkItemTransition {
                $transition = $this->count(null)
                    ->state(['work_item_id' => $workItem->id])
                    ->create($attributes);

                if (! $transition instanceof WorkItemTransition) {
                    throw new LogicException('The transition factory did not create one history event.');
                }

                return $transition;
            },
        );
    }
}
