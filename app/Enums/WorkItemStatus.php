<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkItemStatus: string
{
    case NotStarted = 'not_started';
    case DocumentsRequested = 'documents_requested';
    case AwaitingClient = 'awaiting_client';
    case ReadyForPreparation = 'ready_for_preparation';
    case InPreparation = 'in_preparation';
    case UnderReview = 'under_review';
    case ReturnedForChanges = 'returned_for_changes';
    case AwaitingClientApproval = 'awaiting_client_approval';
    case ReadyToFile = 'ready_to_file';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::DocumentsRequested => 'Documents requested',
            self::AwaitingClient => 'Awaiting client',
            self::ReadyForPreparation => 'Ready for preparation',
            self::InPreparation => 'In preparation',
            self::UnderReview => 'Under review',
            self::ReturnedForChanges => 'Returned for changes',
            self::AwaitingClientApproval => 'Awaiting client approval',
            self::ReadyToFile => 'Ready to file',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::NotStarted => 'zinc',
            self::DocumentsRequested,
            self::AwaitingClient,
            self::AwaitingClientApproval => 'amber',
            self::ReadyForPreparation,
            self::InPreparation => 'blue',
            self::UnderReview,
            self::ReturnedForChanges => 'purple',
            self::ReadyToFile => 'cyan',
            self::Completed => 'green',
            self::Cancelled => 'red',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NotStarted => [
                self::DocumentsRequested,
                self::ReadyForPreparation,
                self::InPreparation,
                self::Cancelled,
            ],
            self::DocumentsRequested => [self::AwaitingClient, self::ReadyForPreparation, self::Cancelled],
            self::AwaitingClient => [self::ReadyForPreparation, self::Cancelled],
            self::ReadyForPreparation => [self::InPreparation, self::Cancelled],
            self::InPreparation => [self::UnderReview, self::Cancelled],
            self::UnderReview => [
                self::ReturnedForChanges,
                self::AwaitingClientApproval,
                self::Cancelled,
            ],
            self::ReturnedForChanges => [self::InPreparation, self::Cancelled],
            self::AwaitingClientApproval => [self::ReadyToFile, self::Cancelled],
            self::ReadyToFile => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function transitionRole(self $target): AssignmentRole
    {
        if ($target === self::Cancelled || $target === self::Completed) {
            return AssignmentRole::ResponsibleManager;
        }

        return match ($this) {
            self::UnderReview, self::AwaitingClientApproval => AssignmentRole::Reviewer,
            default => AssignmentRole::Preparer,
        };
    }
}
