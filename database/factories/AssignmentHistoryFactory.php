<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssignmentRole;
use App\Models\AssignmentHistory;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use App\Models\WorkItem;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<AssignmentHistory>
 */
class AssignmentHistoryFactory extends Factory
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
            'assignment_role' => AssignmentRole::Preparer,
            'assigned_membership_id' => null,
            'assigned_by' => User::factory(),
            'reason' => 'Synthetic assignment fixture.',
            'assigned_at' => now(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function createForWorkItem(
        Firm $firm,
        WorkItem $workItem,
        FirmMembership $membership,
        array $attributes = [],
    ): AssignmentHistory {
        if ($workItem->firm_id !== $firm->id || $membership->firm_id !== $firm->id) {
            throw new LogicException('The assignment must use one firm boundary.');
        }

        return app(FirmContext::class)->runForFirm(
            $firm,
            function () use ($workItem, $membership, $attributes): AssignmentHistory {
                $history = $this->count(null)->state([
                    'work_item_id' => $workItem->id,
                    'assigned_membership_id' => $membership->id,
                ])->create($attributes);

                if (! $history instanceof AssignmentHistory) {
                    throw new LogicException('The assignment factory did not create one history event.');
                }

                return $history;
            },
        );
    }
}
