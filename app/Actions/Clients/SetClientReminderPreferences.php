<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Enums\ClientReminderMode;
use App\Enums\FirmRole;
use App\Models\Client;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SetClientReminderPreferences
{
    public function __construct(
        private FirmContext $firmContext,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $actor,
        Client $client,
        ClientReminderMode $documents,
        ClientReminderMode $vat,
        ClientReminderMode $corporateTax,
        bool $confirmAutomatic = false,
    ): Client {
        $membership = $this->firmContext->membership();

        if (
            $membership === null
            || $membership->user_id !== $actor->getKey()
            || $membership->role !== FirmRole::FirmAdministrator
            || $client->firm_id !== $this->firmContext->firmId()
        ) {
            throw new AuthorizationException('Only a firm administrator may change client reminder preferences.');
        }

        $usesAutomatic = in_array(ClientReminderMode::Automatic, [$documents, $vat, $corporateTax], true);

        if ($usesAutomatic && blank($client->primary_email)) {
            throw ValidationException::withMessages([
                'primary_email' => 'Add a primary client email before enabling automatic reminders.',
            ]);
        }

        if ($usesAutomatic && $client->automatic_reminders_confirmed_at === null && ! $confirmAutomatic) {
            throw ValidationException::withMessages([
                'automatic_reminders' => 'Confirm that this client has approved automatic reminder emails.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $client,
            $confirmAutomatic,
            $corporateTax,
            $documents,
            $usesAutomatic,
            $vat,
        ): Client {
            $locked = $client->newQuery()->whereKey($client->getKey())->lockForUpdate()->firstOrFail();
            $before = [
                'documents' => $locked->document_reminder_mode->value,
                'vat' => $locked->vat_reminder_mode->value,
                'corporate_tax' => $locked->corporate_tax_reminder_mode->value,
            ];

            $locked->forceFill([
                'document_reminder_mode' => $documents,
                'vat_reminder_mode' => $vat,
                'corporate_tax_reminder_mode' => $corporateTax,
                'automatic_reminders_confirmed_at' => $usesAutomatic && $confirmAutomatic
                    ? Date::now()
                    : $locked->automatic_reminders_confirmed_at,
                'automatic_reminders_confirmed_by' => $usesAutomatic && $confirmAutomatic
                    ? $actor->getKey()
                    : $locked->automatic_reminders_confirmed_by,
            ])->save();

            $this->recordAudit->handle(
                action: 'client.reminder_preferences_changed',
                actor: $actor,
                auditable: $locked,
                before: $before,
                after: [
                    'documents' => $documents->value,
                    'vat' => $vat->value,
                    'corporate_tax' => $corporateTax->value,
                    'automatic_confirmed' => $locked->automatic_reminders_confirmed_at !== null,
                ],
            );

            return $locked->refresh();
        });
    }
}
