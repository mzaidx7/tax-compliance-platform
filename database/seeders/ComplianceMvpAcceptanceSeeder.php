<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tenancy\CreateFirmMembership;
use App\Enums\AssignmentRole;
use App\Enums\FirmRole;
use App\Enums\WorkItemStatus;
use App\Models\AssignmentHistory;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistVersion;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkItem;
use App\Models\WorkItemChecklist;
use App\Tenancy\FirmContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use LogicException;

final class ComplianceMvpAcceptanceSeeder extends Seeder
{
    public const CLIENT_COUNT = 200;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('The synthetic acceptance fixture cannot run in production.');
        }

        if (Firm::query()->exists()) {
            throw new LogicException('The synthetic acceptance fixture requires a clean database.');
        }

        $firm = Firm::factory()->create([
            'name' => 'Synthetic 200 Client Acceptance Practice',
            'slug' => 'synthetic-acceptance-practice',
        ]);
        $users = [
            'administrator' => $this->user('Synthetic Acceptance Administrator', 'administrator@example.test'),
            'manager' => $this->user('Synthetic Acceptance Manager', 'acceptance-manager@example.test'),
            'preparer' => $this->user('Synthetic Acceptance Preparer', 'acceptance-preparer@example.test'),
            'reviewer' => $this->user('Synthetic Acceptance Reviewer', 'acceptance-reviewer@example.test'),
        ];
        $memberships = [
            'administrator' => app(CreateFirmMembership::class)->handle($firm, $users['administrator'], FirmRole::FirmAdministrator),
            'manager' => app(CreateFirmMembership::class)->handle($firm, $users['manager'], FirmRole::Manager),
            'preparer' => app(CreateFirmMembership::class)->handle($firm, $users['preparer'], FirmRole::Preparer),
            'reviewer' => app(CreateFirmMembership::class)->handle($firm, $users['reviewer'], FirmRole::Reviewer),
        ];

        $this->call([WorkflowDefinitionSeeder::class, ChecklistSeeder::class]);
        $workflow = WorkflowDefinition::withoutGlobalScopes()
            ->where('firm_id', $firm->id)->where('status', 'published')->firstOrFail();
        $checklist = ChecklistVersion::withoutGlobalScopes()
            ->where('firm_id', $firm->id)
            ->whereHas('template', static fn ($query) => $query->where('template_key', ChecklistTemplate::CORE_KEY))
            ->where('status', 'published')->firstOrFail();
        $statuses = [
            WorkItemStatus::NotStarted,
            WorkItemStatus::AwaitingClient,
            WorkItemStatus::InPreparation,
            WorkItemStatus::UnderReview,
            WorkItemStatus::AwaitingClientApproval,
            WorkItemStatus::ReadyToFile,
        ];

        for ($number = 1; $number <= self::CLIENT_COUNT; $number++) {
            $code = sprintf('ACC-%04d', $number);
            $client = Client::factory()->createForFirm($firm, [
                'internal_code' => $code,
                'internal_code_normalized' => $code,
                'legal_name' => "Synthetic Acceptance Client {$number} LLC",
                'trade_name' => null,
                'created_by' => $users['administrator']->id,
            ]);
            $taxType = $number % 2 === 0 ? 'Corporate Tax' : 'VAT';
            $dueDate = sprintf('2027-%02d-%02d', (($number - 1) % 12) + 1, (($number - 1) % 27) + 1);
            $obligation = Obligation::factory()->createForFirm($firm, $client, [
                'obligation_type' => "Synthetic manual {$taxType} review",
                'period_label' => "Synthetic {$taxType} period {$number}",
                'statutory_due_date' => $dueDate,
                'internal_target_date' => CarbonImmutable::parse($dueDate)->subDays(7)->toDateString(),
                'last_verified_on' => '2026-07-28',
                'verified_by' => $users['manager']->id,
                'created_by' => $users['administrator']->id,
            ]);
            $workItem = WorkItem::factory()->createForFirm($firm, $obligation, [
                'workflow_definition_id' => $workflow->id,
                'status' => $statuses[($number - 1) % count($statuses)],
                'created_by' => $users['manager']->id,
            ]);
            app(FirmContext::class)->runForFirm($firm, static fn (): WorkItemChecklist => WorkItemChecklist::query()->create([
                'work_item_id' => $workItem->id,
                'checklist_version_id' => $checklist->id,
                'applied_by' => $users['manager']->id,
                'applied_at' => now('UTC'),
            ]));

            if ($number % 10 !== 0) {
                $this->assign($firm, $workItem, $memberships['preparer'], AssignmentRole::Preparer, $users['manager']);
                $this->assign($firm, $workItem, $memberships['reviewer'], AssignmentRole::Reviewer, $users['manager']);
                $this->assign($firm, $workItem, $memberships['manager'], AssignmentRole::ResponsibleManager, $users['manager']);
            }
        }
    }

    private function user(string $name, string $email): User
    {
        return User::factory()->create(['name' => $name, 'email' => $email]);
    }

    private function assign(
        Firm $firm,
        WorkItem $workItem,
        FirmMembership $membership,
        AssignmentRole $role,
        User $manager,
    ): void {
        AssignmentHistory::factory()->createForWorkItem($firm, $workItem, $membership, [
            'assignment_role' => $role,
            'assigned_by' => $manager->id,
            'reason' => 'Deterministic synthetic acceptance assignment.',
        ]);
    }
}
