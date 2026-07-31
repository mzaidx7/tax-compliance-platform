<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Enums\ClientReminderStatus;
use App\Jobs\SendClientReminderEmail;
use App\Models\ClientReminderRequest;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class ApproveClientReminder
{
    public function __construct(
        private FirmContext $firmContext,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, ClientReminderRequest $request): ClientReminderRequest
    {
        Gate::forUser($actor)->authorize('update', $request->client);

        if ($request->firm_id !== $this->firmContext->firmId()) {
            abort(403);
        }

        return DB::transaction(function () use ($actor, $request): ClientReminderRequest {
            $locked = ClientReminderRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== ClientReminderStatus::AwaitingReview) {
                throw ValidationException::withMessages([
                    'reminder' => 'Only reminders awaiting review can be approved.',
                ]);
            }
            if (blank($locked->client->primary_email)) {
                throw ValidationException::withMessages([
                    'primary_email' => 'Add a primary client email before approving this reminder.',
                ]);
            }

            $locked->update([
                'status' => ClientReminderStatus::Queued,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now('UTC'),
            ]);

            $this->recordAudit->handle(
                action: 'client.reminder_approved',
                actor: $actor,
                auditable: $locked,
                after: [
                    'client_id' => $locked->client_id,
                    'category' => $locked->category->value,
                    'event_date' => $locked->event_date->toDateString(),
                ],
            );

            SendClientReminderEmail::dispatch($locked->firm_id, $locked->id);

            return $locked->refresh();
        });
    }
}
