<?php

declare(strict_types=1);

namespace App\Actions\Exports;

use App\Data\ExportArtifact;
use App\Enums\Permission;
use App\Models\Client;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final readonly class ExportClientMasterData
{
    public function __construct(private FirmContext $firmContext, private CreateCsvExport $createCsvExport) {}

    public function handle(User $actor): ExportArtifact
    {
        $membership = $this->firmContext->membership();
        if ($membership === null || $membership->user_id !== $actor->id) {
            throw new AuthorizationException('An active firm member is required.');
        }

        $query = Client::query()->with(['contacts', 'people', 'taxRegistrations.periods']);
        if (! $membership->hasPermission(Permission::ManageClients)) {
            $query->whereHas('serviceEnrollments', static fn (Builder $enrollments): Builder => $enrollments
                ->where('responsible_membership_id', $membership->id));
        }

        return $this->createCsvExport->handle(
            name: 'client-master',
            headers: [
                'internal_code', 'legal_name', 'trade_name', 'entity_type', 'email', 'mobile', 'vat_trn',
                'corporate_tax_trn', 'vat_frequency', 'vat_period_start', 'vat_period_end',
                'corporate_tax_period_start', 'corporate_tax_period_end', 'trade_license_number',
                'trade_license_authority', 'trade_license_issue_date', 'trade_license_expiry_date',
                'passport_number', 'passport_expiry_date', 'emirates_id_number', 'emirates_id_expiry_date',
                'contacts', 'people', 'tax_periods',
            ],
            rows: $this->rows($query),
            actor: $actor,
        );
    }

    /**
     * @param  Builder<Client>  $query
     * @return iterable<int, list<string|null>>
     */
    private function rows(Builder $query): iterable
    {
        foreach ($query->orderBy('legal_name')->lazy(200) as $client) {
            yield [
                $client->internal_code,
                $client->legal_name,
                $client->trade_name,
                $client->entity_type,
                $client->primary_email,
                $client->primary_phone,
                $client->vat_trn,
                $client->corporate_tax_trn,
                $client->vat_frequency,
                $client->vat_period_starts_on?->toDateString(),
                $client->vat_period_ends_on?->toDateString(),
                $client->corporate_tax_period_starts_on?->toDateString(),
                $client->corporate_tax_period_ends_on?->toDateString(),
                $client->trade_license_number,
                $client->trade_license_authority,
                $client->trade_license_issued_on?->toDateString(),
                $client->trade_license_expires_on?->toDateString(),
                $client->passport_number,
                $client->passport_expires_on?->toDateString(),
                $client->emirates_id_number,
                $client->emirates_id_expires_on?->toDateString(),
                $client->contacts->map(fn ($contact): string => trim($contact->name.' <'.($contact->email ?? $contact->phone ?? '').'>'))->implode('; '),
                $client->people->map(fn ($person): string => trim($person->name.' ('.$person->role.') passport: '.($person->passport_number ?? '').' Emirates ID: '.($person->emirates_id_number ?? '')))->implode('; '),
                $client->taxRegistrations->flatMap(fn ($registration) => $registration->periods->map(fn ($period): string => $registration->tax_type->label().' '.$period->starts_on->toDateString().' to '.$period->ends_on->toDateString()))->implode('; '),
            ];
        }
    }
}
