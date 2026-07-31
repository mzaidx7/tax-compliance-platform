<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Enums\ClientReminderCategory;
use App\Enums\ClientReminderMode;
use App\Enums\ClientReminderStatus;
use App\Enums\ObligationStatus;
use App\Jobs\SendClientReminderEmail;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientReminderRequest;
use App\Models\Obligation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

final class GenerateClientReminderRequests
{
    /** @var list<int> */
    private const DOCUMENT_DAYS = [60, 30, 14, 7];

    public function handle(CarbonImmutable $asOf): int
    {
        $date = $asOf->startOfDay();
        $generated = 0;

        $this->generateDocuments($date, $generated);
        $this->generateObligations($date, $generated);

        return $generated;
    }

    private function generateDocuments(CarbonImmutable $date, int &$generated): void
    {
        ClientDocument::query()
            ->with(['client', 'documentTypeVersion'])
            ->whereNotNull('expires_on')
            ->whereDoesntHave('successor')
            ->whereIn('expires_on', array_map(
                static fn (int $days): string => $date->addDays($days)->toDateString(),
                self::DOCUMENT_DAYS,
            ))
            ->orderBy('id')
            ->chunkById(100, function ($documents) use ($date, &$generated): void {
                foreach ($documents as $document) {
                    $days = (int) $date->diffInDays($document->expires_on, false);
                    $generated += $this->create(
                        $document->client,
                        ClientReminderCategory::Documents,
                        $document->client->document_reminder_mode,
                        $document,
                        CarbonImmutable::parse($document->expires_on->toDateString()),
                        $days,
                        $date,
                    );
                }
            });
    }

    private function generateObligations(CarbonImmutable $date, int &$generated): void
    {
        Obligation::query()
            ->with('client')
            ->where('status', ObligationStatus::Open)
            ->where(function ($query) use ($date): void {
                $query->whereDate('effective_due_date', $date->addDays(30)->toDateString())
                    ->orWhere(function ($fallback) use ($date): void {
                        $fallback->whereNull('effective_due_date')
                            ->whereDate('statutory_due_date', $date->addDays(30)->toDateString());
                    })
                    ->orWhereDate('effective_due_date', $date->addDays(240)->toDateString())
                    ->orWhere(function ($fallback) use ($date): void {
                        $fallback->whereNull('effective_due_date')
                            ->whereDate('statutory_due_date', $date->addDays(240)->toDateString());
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($obligations) use ($date, &$generated): void {
                foreach ($obligations as $obligation) {
                    $category = match ($obligation->obligation_type) {
                        'VAT Return' => ClientReminderCategory::Vat,
                        'Corporate Tax Return' => ClientReminderCategory::CorporateTax,
                        default => null,
                    };

                    if ($category === null) {
                        continue;
                    }

                    $due = CarbonImmutable::parse($obligation->effectiveDueDate()->toDateString());
                    $days = $category === ClientReminderCategory::Vat ? 30 : 240;
                    if (! $date->addDays($days)->isSameDay($due)) {
                        continue;
                    }

                    $mode = $category === ClientReminderCategory::Vat
                        ? $obligation->client->vat_reminder_mode
                        : $obligation->client->corporate_tax_reminder_mode;
                    $generated += $this->create(
                        $obligation->client,
                        $category,
                        $mode,
                        $obligation,
                        $due,
                        $days,
                        $date,
                    );
                }
            });
    }

    private function create(
        Client $client,
        ClientReminderCategory $category,
        ClientReminderMode $mode,
        Model $source,
        CarbonImmutable $eventDate,
        int $daysBefore,
        CarbonImmutable $scheduledFor,
    ): int {
        if ($mode === ClientReminderMode::Off) {
            return 0;
        }

        $key = hash('sha256', implode('|', [
            'client-reminder:v1',
            $client->firm_id,
            $client->id,
            $category->value,
            $source->getMorphClass(),
            (string) $source->getKey(),
            (string) $daysBefore,
            $scheduledFor->toDateString(),
        ]));
        $status = blank($client->primary_email)
            ? ClientReminderStatus::Blocked
            : ($mode === ClientReminderMode::Automatic
                ? ClientReminderStatus::Queued
                : ClientReminderStatus::AwaitingReview);

        $request = ClientReminderRequest::query()->firstOrCreate(
            ['deterministic_key' => $key],
            [
                'client_id' => $client->id,
                'category' => $category,
                'status' => $status,
                'source_type' => $source->getMorphClass(),
                'source_id' => (string) $source->getKey(),
                'event_date' => $eventDate->toDateString(),
                'days_before' => $daysBefore,
                'scheduled_for' => $scheduledFor->toDateString(),
                'failure_code' => $status === ClientReminderStatus::Blocked ? 'missing_primary_email' : null,
            ],
        );

        if ($request->wasRecentlyCreated && $status === ClientReminderStatus::Queued) {
            SendClientReminderEmail::dispatch($client->firm_id, $request->id);
        }

        return $request->wasRecentlyCreated ? 1 : 0;
    }
}
