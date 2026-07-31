<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Actions\Exports\CreateCsvExport;
use App\Data\ExportArtifact;
use App\Enums\AssignmentRole;
use App\Enums\OperationalReportType;
use App\Enums\Permission;
use App\Enums\WorkItemStatus;
use App\Models\ClientDocument;
use App\Models\Obligation;
use App\Models\TaxPeriod;
use App\Models\User;
use App\Models\WorkItem;
use App\Tenancy\FirmContext;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final readonly class BuildOperationalReport
{
    public function __construct(private FirmContext $context, private CreateCsvExport $createCsvExport) {}

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|bool|null>>, truncated: bool}
     */
    public function preview(User $actor, OperationalReportType $type, string $month): array
    {
        $this->authorize($actor);
        [$start, $end] = $this->monthRange($month);
        $rows = [];

        foreach ($this->rows($type, $start, $end) as $row) {
            if (count($rows) === 101) {
                break;
            }
            $rows[] = $row;
        }

        return [
            'headers' => $this->headers($type),
            'rows' => array_slice($rows, 0, 100),
            'truncated' => count($rows) > 100,
        ];
    }

    public function export(User $actor, OperationalReportType $type, string $month): ExportArtifact
    {
        $this->authorize($actor);
        [$start, $end] = $this->monthRange($month);

        return $this->createCsvExport->handle(
            name: "{$type->exportSlug()}-{$start->format('Y-m')}",
            headers: $this->headers($type),
            rows: $this->rows($type, $start, $end),
            actor: $actor,
        );
    }

    /** @return list<string> */
    private function headers(OperationalReportType $type): array
    {
        return match ($type) {
            OperationalReportType::MonthlySchedule => [
                'client_code', 'client_name', 'obligation_type', 'period_label',
                'effective_due_date', 'statutory_due_date', 'obligation_status',
            ],
            OperationalReportType::TaxPeriods => [
                'client_code', 'client_name', 'tax_type', 'period_label', 'starts_on', 'ends_on', 'period_status',
            ],
            OperationalReportType::ExpiringDocuments => [
                'client_code', 'client_name', 'document_type', 'expires_on', 'timing',
            ],
            OperationalReportType::WorkloadCompletion => [
                'client_code', 'client_name', 'obligation_type', 'period_label', 'work_status',
                'risk_status', 'preparer', 'reviewer', 'responsible_manager', 'completed_on',
            ],
        };
    }

    /**
     * @return Generator<int, list<string|int|float|bool|null>>
     */
    private function rows(OperationalReportType $type, CarbonImmutable $start, CarbonImmutable $end): Generator
    {
        $models = match ($type) {
            OperationalReportType::MonthlySchedule => Obligation::query()
                ->with('client')
                ->where(function ($query) use ($start, $end): void {
                    $query
                        ->where(function ($query) use ($start, $end): void {
                            $query
                                ->whereNotNull('effective_due_date')
                                ->whereDate('effective_due_date', '>=', $start->toDateString())
                                ->whereDate('effective_due_date', '<=', $end->toDateString());
                        })
                        ->orWhere(function ($query) use ($start, $end): void {
                            $query
                                ->whereNull('effective_due_date')
                                ->whereDate('statutory_due_date', '>=', $start->toDateString())
                                ->whereDate('statutory_due_date', '<=', $end->toDateString());
                        });
                })
                ->orderByRaw('coalesce(effective_due_date, statutory_due_date)')
                ->orderBy('id')->lazy(500),
            OperationalReportType::TaxPeriods => TaxPeriod::query()
                ->with('registration.client')
                ->whereDate('starts_on', '<=', $end->toDateString())
                ->whereDate('ends_on', '>=', $start->toDateString())
                ->orderBy('starts_on')->orderBy('id')->lazy(500),
            OperationalReportType::ExpiringDocuments => ClientDocument::query()
                ->with(['client', 'documentTypeVersion'])
                ->whereNotExists(static fn ($query) => $query
                    ->selectRaw('1')
                    ->from('client_documents as successor_documents')
                    ->whereColumn('successor_documents.firm_id', 'client_documents.firm_id')
                    ->whereColumn('successor_documents.supersedes_client_document_id', 'client_documents.id'))
                ->whereDate('expires_on', '>=', $start->toDateString())
                ->whereDate('expires_on', '<=', $end->toDateString())
                ->orderBy('expires_on')->orderBy('id')->lazy(500),
            OperationalReportType::WorkloadCompletion => WorkItem::query()
                ->with(['obligation.client', 'assignmentHistories.assignedMembership.user', 'transitions'])
                ->orderBy('status')->orderBy('id')->lazy(500),
        };

        foreach ($models as $model) {
            yield $this->row($type, $model);
        }
    }

    /** @return list<string|int|float|bool|null> */
    private function row(OperationalReportType $type, Model $model): array
    {
        if ($type === OperationalReportType::MonthlySchedule && $model instanceof Obligation) {
            return [
                $model->client->internal_code, $model->client->legal_name, $model->obligation_type,
                $model->period_label, $model->effectiveDueDate()->toDateString(),
                $model->statutory_due_date->toDateString(), $model->status->label(),
            ];
        }
        if ($type === OperationalReportType::TaxPeriods && $model instanceof TaxPeriod) {
            return [
                $model->registration->client->internal_code, $model->registration->client->legal_name,
                $model->registration->tax_type->label(), $model->label, $model->starts_on->toDateString(),
                $model->ends_on->toDateString(), $model->status->label(),
            ];
        }
        if ($type === OperationalReportType::ExpiringDocuments && $model instanceof ClientDocument) {
            return [
                $model->client->internal_code, $model->client->legal_name, $model->documentTypeVersion->name,
                $model->expires_on?->toDateString(),
                $model->expires_on?->isPast() ? 'Past recorded expiry date' : 'Upcoming recorded expiry date',
            ];
        }
        if ($type === OperationalReportType::WorkloadCompletion && $model instanceof WorkItem) {
            $completed = $model->transitions
                ->where('to_status', WorkItemStatus::Completed)
                ->sortByDesc('transitioned_at')
                ->first();

            return [
                $model->obligation->client->internal_code, $model->obligation->client->legal_name,
                $model->obligation->obligation_type, $model->obligation->period_label,
                $model->status->label(), $model->risk_status->label(),
                $model->currentAssignment(AssignmentRole::Preparer)?->assignedMembership?->user?->name,
                $model->currentAssignment(AssignmentRole::Reviewer)?->assignedMembership?->user?->name,
                $model->currentAssignment(AssignmentRole::ResponsibleManager)?->assignedMembership?->user?->name,
                $completed?->transitioned_at->toDateString(),
            ];
        }

        throw new \LogicException('The operational report row type is invalid.');
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function monthRange(string $month): array
    {
        /** @var array{month: string} $validated */
        $validated = Validator::make(['month' => $month], ['month' => ['required', 'date_format:Y-m']])->validate();
        $start = CarbonImmutable::createFromFormat('!Y-m', $validated['month'])
            ?: throw new \InvalidArgumentException('The report month is invalid.');

        return [$start->startOfMonth(), $start->endOfMonth()];
    }

    private function authorize(User $actor): void
    {
        $membership = $this->context->membership();
        if ($membership?->user_id !== $actor->id || ! $membership->hasPermission(Permission::ViewReports)) {
            throw new AuthorizationException('Report permission is required.');
        }
    }
}
