<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ClientReminderStatus;
use App\Jobs\Middleware\SetFirmContext;
use App\Models\ClientDocument;
use App\Models\ClientReminderAttempt;
use App\Models\ClientReminderRequest;
use App\Models\Obligation;
use App\Notifications\ClientComplianceReminder;
use App\Tenancy\FirmContext;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

final class SendClientReminderEmail implements FirmAwareJob, ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        private readonly string $firmId,
        private readonly string $requestId,
    ) {
        $this->onQueue((string) config('platform.queue.name', 'platform'));
        $this->afterCommit();
    }

    public function firmId(): string
    {
        return $this->firmId;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new SetFirmContext];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(FirmContext $context): void
    {
        $request = ClientReminderRequest::query()
            ->with(['client', 'source'])
            ->findOrFail($this->requestId);

        if ($request->status !== ClientReminderStatus::Queued) {
            return;
        }

        $email = trim((string) $request->client->primary_email);
        if ($email === '') {
            $request->update([
                'status' => ClientReminderStatus::Blocked,
                'failure_code' => 'missing_primary_email',
            ]);

            return;
        }

        try {
            [$itemName, $personName] = $this->content($request->source);
            Notification::route('mail', $email)->notify(new ClientComplianceReminder(
                $context->firm()->name,
                $request->client->legal_name,
                $request->category,
                $itemName,
                $personName,
                $request->event_date->format('d M Y'),
            ));

            ClientReminderAttempt::query()->create([
                'client_reminder_request_id' => $request->id,
                'status' => 'sent',
                'attempted_at' => Date::now(),
            ]);
            $request->update([
                'status' => ClientReminderStatus::Sent,
                'sent_at' => Date::now(),
                'failure_code' => null,
            ]);
        } catch (Throwable $exception) {
            ClientReminderAttempt::query()->create([
                'client_reminder_request_id' => $request->id,
                'status' => 'failed',
                'failure_code' => 'delivery_failed',
                'attempted_at' => Date::now(),
            ]);
            $request->update([
                'status' => ClientReminderStatus::Failed,
                'failure_code' => 'delivery_failed',
            ]);

            throw $exception;
        }
    }

    /** @return array{string, string|null} */
    private function content(?Model $source): array
    {
        if ($source instanceof ClientDocument) {
            $source->loadMissing(['documentTypeVersion', 'person']);

            return [$source->documentTypeVersion->name, $source->person?->name];
        }

        if ($source instanceof Obligation) {
            return [$source->obligation_type, null];
        }

        throw new RuntimeException('The client reminder source is not supported.');
    }
}
