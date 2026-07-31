<?php

declare(strict_types=1);

namespace App\Livewire\Tutorial;

use App\Models\Client;
use App\Models\Obligation;
use App\Models\User;
use App\Models\WorkItem;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Help and tutorial')]
final class Index extends Component
{
    public const STEP_COUNT = 6;

    #[Url(as: 'step', history: true)]
    public int $step = 1;

    public bool $completed = false;

    public function mount(): void
    {
        $this->step = $this->normaliseStep($this->step);
        $this->completed = $this->currentUser()->tutorial_completed_at !== null;
    }

    public function goToStep(int $step): void
    {
        $this->step = $this->normaliseStep($step);
    }

    public function previousStep(): void
    {
        $this->step = $this->normaliseStep($this->step - 1);
    }

    public function nextStep(): void
    {
        $this->step = $this->normaliseStep($this->step + 1);
    }

    public function completeTutorial(): void
    {
        $user = $this->currentUser();
        $user->forceFill([
            'tutorial_prompt_dismissed_at' => $user->tutorial_prompt_dismissed_at ?? now(),
            'tutorial_completed_at' => now(),
        ])->save();

        $this->completed = true;
        Flux::toast(__('Tutorial completed. You can return here at any time.'));
    }

    public function restartTutorial(): void
    {
        $this->step = 1;
    }

    public function render(): View
    {
        return view('livewire.tutorial.index', [
            'steps' => $this->steps(),
        ]);
    }

    /**
     * @return list<array{
     *     title: string,
     *     short_title: string,
     *     description: string,
     *     outcome: string,
     *     points: list<string>,
     *     icon: string,
     *     action_label: string,
     *     action_url: string,
     *     action_available: bool
     * }>
     */
    private function steps(): array
    {
        return [
            [
                'title' => __('Start with the portfolio dashboard'),
                'short_title' => __('Dashboard'),
                'description' => __('The dashboard combines every client into one operational view. Start here instead of opening clients one by one.'),
                'outcome' => __('You can identify the month, client or category that needs attention first.'),
                'points' => [
                    __('Choose a due month to group VAT, Corporate Tax and document dates.'),
                    __('Filter by client, tax type, team member or overdue status.'),
                    __('Save useful filter combinations for month-end and quarter-end work.'),
                ],
                'icon' => 'home',
                'action_label' => __('Open dashboard'),
                'action_url' => route('dashboard'),
                'action_available' => true,
            ],
            [
                'title' => __('Add clients and responsible people'),
                'short_title' => __('Client records'),
                'description' => __('Client records hold registration details, VAT and Corporate Tax periods, responsible people and the documents your team monitors.'),
                'outcome' => __('You can create one reliable client record or onboard many clients from the protected template.'),
                'points' => [
                    __('Download the Excel template from the Clients page.'),
                    __('Complete the Clients and People sheets without changing their headings.'),
                    __('Preview the import, correct any highlighted rows, then confirm the upload.'),
                ],
                'icon' => 'building-office-2',
                'action_label' => __('Open clients'),
                'action_url' => route('clients.index'),
                'action_available' => Gate::allows('viewAny', Client::class),
            ],
            [
                'title' => __('Monitor documents before they expire'),
                'short_title' => __('Documents'),
                'description' => __('The document register brings trade licences, passports and Emirates IDs into one renewal queue across the firm.'),
                'outcome' => __('Your team can contact the client before renewal work becomes urgent.'),
                'points' => [
                    __('Use Document expiry to see upcoming and overdue renewals.'),
                    __('Open the client workspace to confirm the document holder and expiry date.'),
                    __('Review prepared reminder emails before anything is sent externally.'),
                ],
                'icon' => 'document-text',
                'action_label' => __('Open document expiry'),
                'action_url' => route('documents.index'),
                'action_available' => Gate::allows('viewAny', Client::class),
            ],
            [
                'title' => __('Understand tax periods and due dates'),
                'short_title' => __('Tax deadlines'),
                'description' => __('Each VAT or Corporate Tax deadline keeps the tax period, filing due date and your team target date separate.'),
                'outcome' => __('You can see what is legally due, what your team aims to finish early and where a date needs review.'),
                'points' => [
                    __('VAT deadlines are generated from the recorded VAT period and filing frequency.'),
                    __('Corporate Tax filing dates are generated from the recorded tax period end.'),
                    __('Any manual date change keeps the original value and reason in activity history.'),
                ],
                'icon' => 'calendar-days',
                'action_label' => __('Open tax deadlines'),
                'action_url' => route('obligations.index'),
                'action_available' => Gate::allows('viewAny', Obligation::class),
            ],
            [
                'title' => __('Plan the month and move client tasks forward'),
                'short_title' => __('Calendar and tasks'),
                'description' => __('The calendar answers when work is due. Client tasks answer who is responsible and what still needs to happen.'),
                'outcome' => __('You can balance workloads and move each job from preparation through review and completion.'),
                'points' => [
                    __('Switch the calendar between Month, Week and List views.'),
                    __('Assign the preparer, reviewer and responsible manager.'),
                    __('Use the checklist and task status to show real progress.'),
                ],
                'icon' => 'queue-list',
                'action_label' => __('Open client tasks'),
                'action_url' => route('work-items.index'),
                'action_available' => Gate::allows('viewAny', WorkItem::class),
            ],
            [
                'title' => __('Review evidence and keep the audit trail clear'),
                'short_title' => __('Review and evidence'),
                'description' => __('Notifications, reports and activity history help reviewers confirm what changed, who approved it and what needs follow-up.'),
                'outcome' => __('You can complete work with a clear record for internal review.'),
                'points' => [
                    __('Use Notifications for prepared reminders and operational alerts.'),
                    __('Use Reports for management views and protected exports.'),
                    __('Use Activity history to trace significant changes without editing the evidence.'),
                ],
                'icon' => 'shield-check',
                'action_label' => __('Open notifications'),
                'action_url' => route('notifications.index'),
                'action_available' => true,
            ],
        ];
    }

    private function normaliseStep(int $step): int
    {
        return max(1, min(self::STEP_COUNT, $step));
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
