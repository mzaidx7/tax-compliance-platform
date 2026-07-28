<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Models\Client;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CommitClientCsvImport
{
    public function __construct(
        private CreateClient $createClient,
        private FirmContext $firmContext,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @param list<array{
     *   line: int, internalCode: string, legalName: string, tradeName: string,
     *   entityType: string, errors: list<string>, valid: bool
     * }> $rows
     */
    public function handle(User $actor, array $rows): int
    {
        Gate::forUser($actor)->authorize('create', Client::class);

        if ($rows === [] || count($rows) > PreviewClientCsvImport::MAX_ROWS) {
            throw ValidationException::withMessages([
                'clientImportFile' => 'Preview between 1 and 500 valid client rows before committing.',
            ]);
        }

        foreach ($rows as $row) {
            if (! $row['valid'] || $row['errors'] !== []) {
                throw ValidationException::withMessages([
                    'clientImportFile' => 'Resolve every rejected row before committing this file.',
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $rows): int {
            foreach ($rows as $row) {
                $this->createClient->handle($actor, [
                    'internalCode' => $row['internalCode'],
                    'legalName' => $row['legalName'],
                    'tradeName' => $row['tradeName'],
                    'entityType' => $row['entityType'],
                ]);
            }

            $this->recordAudit->handle(
                action: 'client.csv_import_committed',
                actor: $actor,
                auditable: $this->firmContext->firm(),
                after: ['accepted_rows' => count($rows)],
            );

            return count($rows);
        }, 3);
    }
}
