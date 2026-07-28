<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssignmentRole;
use App\Enums\WorkItemStatus;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_definition_id' => WorkflowDefinition::factory(),
            'from_status' => WorkItemStatus::NotStarted,
            'to_status' => WorkItemStatus::InPreparation,
            'assignment_role' => AssignmentRole::Preparer,
            'position' => 1,
        ];
    }
}
