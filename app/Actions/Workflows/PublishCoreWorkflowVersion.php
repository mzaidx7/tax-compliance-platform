<?php

declare(strict_types=1);

namespace App\Actions\Workflows;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\WorkItemStatus;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStep;
use App\Models\WorkItem;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class PublishCoreWorkflowVersion
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, string $name = 'Core compliance workflow'): WorkflowDefinition
    {
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $this->firmContext->firmId())) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('create', WorkItem::class);

        return DB::transaction(function () use ($actor, $name): WorkflowDefinition {
            $nextVersion = ((int) WorkflowDefinition::query()
                ->where('definition_key', WorkflowDefinition::CORE_KEY)
                ->lockForUpdate()
                ->max('version')) + 1;
            $definition = WorkflowDefinition::query()->create([
                'definition_key' => WorkflowDefinition::CORE_KEY,
                'name' => trim($name),
                'version' => $nextVersion,
                'status' => 'published',
                'published_by' => $actor->id,
                'published_at' => now('UTC'),
            ]);
            $position = 1;

            foreach (WorkItemStatus::cases() as $fromStatus) {
                foreach ($fromStatus->allowedTransitions() as $toStatus) {
                    WorkflowStep::query()->create([
                        'workflow_definition_id' => $definition->id,
                        'from_status' => $fromStatus,
                        'to_status' => $toStatus,
                        'assignment_role' => $fromStatus->transitionRole($toStatus),
                        'position' => $position++,
                    ]);
                }
            }

            $this->recordAudit->handle(
                action: 'workflow.version_published',
                actor: $actor,
                auditable: $definition,
                after: [
                    'definition_key' => $definition->definition_key,
                    'version' => $nextVersion,
                    'step_count' => $position - 1,
                ],
            );

            return $definition->load('steps');
        }, 3);
    }
}
