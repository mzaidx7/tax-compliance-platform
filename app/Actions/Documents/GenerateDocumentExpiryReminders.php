<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\ExpiryReminderKind;
use App\Models\ClientDocument;
use App\Models\DocumentExpiryReminder;
use Carbon\CarbonImmutable;

final class GenerateDocumentExpiryReminders
{
    public function handle(CarbonImmutable $asOf): int
    {
        $date = $asOf->startOfDay();
        $generated = 0;

        ClientDocument::query()
            ->with('documentTypeVersion')
            ->whereNotNull('expires_on')
            ->whereDoesntHave('successor')
            ->orderBy('id')
            ->chunkById(100, function ($documents) use ($date, &$generated): void {
                foreach ($documents as $document) {
                    $expiry = CarbonImmutable::parse($document->expires_on->toDateString());
                    $daysUntilExpiry = (int) $date->diffInDays($expiry, false);
                    $kind = $this->kindFor($document, $daysUntilExpiry);

                    if ($kind === null) {
                        continue;
                    }

                    $reminder = DocumentExpiryReminder::query()->firstOrCreate(
                        [
                            'client_document_id' => $document->id,
                            'kind' => $kind,
                            'scheduled_for' => $date,
                        ],
                        [
                            'days_from_expiry' => $daysUntilExpiry,
                            'generated_at' => now('UTC'),
                        ],
                    );

                    if ($reminder->wasRecentlyCreated) {
                        $generated++;
                    }
                }
            });

        return $generated;
    }

    private function kindFor(ClientDocument $document, int $daysUntilExpiry): ?ExpiryReminderKind
    {
        if ($daysUntilExpiry === 0) {
            return ExpiryReminderKind::ExpiryDay;
        }

        if (
            $daysUntilExpiry > 0
            && in_array($daysUntilExpiry, $document->documentTypeVersion->reminder_days, true)
        ) {
            return ExpiryReminderKind::Upcoming;
        }

        $repeatDays = $document->documentTypeVersion->overdue_repeat_days;

        if ($daysUntilExpiry < 0 && is_int($repeatDays) && abs($daysUntilExpiry) % $repeatDays === 0) {
            return ExpiryReminderKind::OverdueEscalation;
        }

        return null;
    }
}
