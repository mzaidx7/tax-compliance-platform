<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Actions\Compliance\GenerateClientComplianceSchedule;
use App\Actions\Documents\RecordClientDocumentMetadata;
use App\Enums\ClientContactPurpose;
use App\Enums\PreferredContactChannel;
use App\Enums\TaxRegistrationStatus;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\ClientPerson;
use App\Models\DocumentTypeVersion;
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
        private RecordClientDocumentMetadata $recordClientDocumentMetadata,
        private GenerateClientComplianceSchedule $generateClientComplianceSchedule,
    ) {}

    /**
     * @param list<array{
     *   line: int, internalCode: string, legalName: string, tradeName: string,
     *   entityType: string, masterData: array<string, string>, errors: list<string>, valid: bool
     * }> $rows
     * @param list<array{
     *   line: int, clientInternalCode: string, name: string, role: string,
     *   data: array<string, string>, errors: list<string>, valid: bool
     * }> $people
     */
    public function handle(User $actor, array $rows, array $people = []): int
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
        foreach ($people as $person) {
            if (! $person['valid'] || $person['errors'] !== []) {
                throw ValidationException::withMessages([
                    'clientImportFile' => 'Resolve every rejected People row before committing this file.',
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $people, $rows): int {
            $clientsByCode = [];
            foreach ($rows as $row) {
                $client = $this->createClient->handle($actor, [
                    'internalCode' => $row['internalCode'],
                    'legalName' => $row['legalName'],
                    'tradeName' => $row['tradeName'],
                    'entityType' => $row['entityType'],
                ]);
                $clientsByCode[$row['internalCode']] = $client;
                $this->applyMasterData($actor, $client, $row['masterData']);
                $this->generateClientComplianceSchedule->handle($actor, $client);
            }
            foreach ($people as $personRow) {
                $client = $clientsByCode[$personRow['clientInternalCode']] ?? null;
                if (! $client instanceof Client) {
                    throw ValidationException::withMessages([
                        'clientImportFile' => "People line {$personRow['line']} does not match an imported client.",
                    ]);
                }
                $this->createPerson($actor, $client, $personRow);
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

    /**
     * @param array{
     *   line: int, clientInternalCode: string, name: string, role: string,
     *   data: array<string, string>, errors: list<string>, valid: bool
     * } $row
     */
    private function createPerson(User $actor, Client $client, array $row): void
    {
        $data = $row['data'];
        $person = ClientPerson::query()->create([
            'client_id' => $client->id,
            'name' => trim($row['name']),
            'role' => trim($row['role']),
            'passport_number' => $data['passport_number'] ?? null,
            'passport_expires_on' => $data['passport_expiry_date'] ?? null,
            'emirates_id_number' => $data['emirates_id_number'] ?? null,
            'emirates_id_expires_on' => $data['emirates_id_expiry_date'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => ! in_array(strtolower($data['is_active'] ?? 'yes'), ['no', '0', 'false'], true),
            'created_by' => $actor->id,
        ]);

        foreach ([
            ['passport', 'Passport', 'passport_expiry_date', 'passport_number'],
            ['emirates_id', 'Emirates ID', 'emirates_id_expiry_date', 'emirates_id_number'],
        ] as [$key, $name, $expiryField, $referenceField]) {
            if (! isset($data[$expiryField])) {
                continue;
            }
            $type = DocumentTypeVersion::query()->firstOrCreate(
                ['key' => $key, 'version' => 1],
                [
                    'name' => $name,
                    'expiry_required' => true,
                    'reminder_days' => [60, 30, 14, 7],
                    'overdue_repeat_days' => 7,
                    'published_at' => now('UTC'),
                    'created_by' => $actor->id,
                ],
            );
            $this->recordClientDocumentMetadata->handle(
                $actor,
                $client,
                $type,
                $data[$referenceField] ?? null,
                null,
                $data[$expiryField],
                null,
                $person,
            );
        }
    }

    /** @param array<string, string> $data */
    private function applyMasterData(User $actor, Client $client, array $data): void
    {
        if ($data === []) {
            return;
        }

        $client->forceFill([
            'primary_email' => $data['email'] ?? null,
            'primary_phone' => $data['mobile'] ?? null,
            'vat_trn' => $data['vat_trn'] ?? null,
            'corporate_tax_trn' => $data['ct_trn'] ?? null,
            'vat_frequency' => $data['vat_frequency'] ?? null,
            'vat_period_starts_on' => $data['vat_period_start'] ?? null,
            'vat_period_ends_on' => $data['vat_period_end'] ?? null,
            'corporate_tax_period_starts_on' => $data['ct_period_start'] ?? null,
            'corporate_tax_period_ends_on' => $data['ct_period_end'] ?? null,
            'trade_license_number' => $data['trade_license_number'] ?? null,
            'trade_license_authority' => $data['trade_license_authority'] ?? null,
            'trade_license_issued_on' => $data['trade_license_issue_date'] ?? null,
            'trade_license_expires_on' => $data['trade_license_expiry_date'] ?? null,
            'passport_number' => $data['passport_number'] ?? null,
            'passport_expires_on' => $data['passport_expiry_date'] ?? null,
            'emirates_id_number' => $data['emirates_id_number'] ?? null,
            'emirates_id_expires_on' => $data['emirates_id_expiry_date'] ?? null,
        ]);
        $client->save();

        if (isset($data['email']) || isset($data['mobile'])) {
            $channel = isset($data['email']) ? PreferredContactChannel::Email : PreferredContactChannel::Phone;
            app(AddClientContact::class)->handle(
                $actor,
                $client,
                $data['authorised_signatory_name'] ?? 'Primary client contact',
                null,
                ClientContactPurpose::Primary,
                $channel,
                $data['email'] ?? null,
                $data['mobile'] ?? null,
            );
        }

        if (isset($data['authorised_signatory_name'])) {
            ClientPerson::query()->firstOrCreate(
                [
                    'client_id' => $client->id,
                    'name' => trim($data['authorised_signatory_name']),
                    'role' => 'authorised_signatory',
                ],
                [
                    'passport_number' => $data['authorised_signatory_passport_number'] ?? null,
                    'passport_expires_on' => $data['authorised_signatory_passport_expiry_date'] ?? null,
                    'emirates_id_number' => $data['authorised_signatory_emirates_id_number'] ?? null,
                    'emirates_id_expires_on' => $data['authorised_signatory_emirates_id_expiry_date'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['mobile'] ?? null,
                    'is_active' => true,
                    'created_by' => $actor->id,
                ],
            );
        }

        foreach ([['vat_trn', TaxType::Vat], ['ct_trn', TaxType::CorporateTax]] as [$field, $type]) {
            if (! isset($data[$field])) {
                continue;
            }
            $registration = app(AddTaxRegistration::class)->handle(
                $actor, $client, $type, $data[$field], TaxRegistrationStatus::Active, null, null,
            );
            $start = $type === TaxType::Vat ? ($data['vat_period_start'] ?? null) : ($data['ct_period_start'] ?? null);
            $end = $type === TaxType::Vat ? ($data['vat_period_end'] ?? null) : ($data['ct_period_end'] ?? null);
            if ($start !== null && $end !== null) {
                app(AddTaxPeriod::class)->handle(
                    $actor,
                    $registration,
                    strtoupper($type->label())." {$start} to {$end}",
                    $start,
                    $end,
                );
            }
        }

        foreach ([
            ['trade_license', 'Trade licence', 'trade_license_expiry_date', 'trade_license_issue_date', 'trade_license_number'],
            ['passport', 'Passport', 'passport_expiry_date', null, 'passport_number'],
            ['emirates_id', 'Emirates ID', 'emirates_id_expiry_date', null, 'emirates_id_number'],
        ] as [$key, $name, $expiryField, $issuedField, $referenceField]) {
            if (! isset($data[$expiryField])) {
                continue;
            }
            $type = DocumentTypeVersion::query()->firstOrCreate(
                ['key' => $key, 'version' => 1],
                [
                    'name' => $name,
                    'expiry_required' => true,
                    'reminder_days' => [90, 60, 30, 14, 7],
                    'overdue_repeat_days' => 7,
                    'published_at' => now('UTC'),
                    'created_by' => $actor->id,
                ],
            );
            $this->recordClientDocumentMetadata->handle(
                $actor,
                $client,
                $type,
                $data[$referenceField] ?? null,
                $issuedField !== null ? ($data[$issuedField] ?? null) : null,
                $data[$expiryField],
            );
        }
    }
}
