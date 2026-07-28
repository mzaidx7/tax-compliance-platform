<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\Client;
use App\Models\DocumentTypeVersion;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final readonly class PublishDocumentTypeVersion
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @param  list<int>  $reminderDays
     */
    public function handle(
        User $actor,
        string $key,
        string $name,
        bool $expiryRequired,
        array $reminderDays,
        ?int $overdueRepeatDays,
    ): DocumentTypeVersion {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ClientMaster, $firmId)) {
            throw new AuthorizationException('The client master is not enabled for this firm.');
        }
        Gate::forUser($actor)->authorize('create', Client::class);

        $normalizedKey = Str::slug(trim($key), '_');
        $normalizedDays = array_values(array_unique($reminderDays));
        rsort($normalizedDays);

        /** @var array{key: string, name: string, reminder_days: list<int>, overdue_repeat_days: int|null} $validated */
        $validated = Validator::make(
            [
                'key' => $normalizedKey,
                'name' => trim($name),
                'reminder_days' => $normalizedDays,
                'overdue_repeat_days' => $overdueRepeatDays,
            ],
            [
                'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
                'name' => ['required', 'string', 'max:100'],
                'reminder_days' => ['array', 'max:12'],
                'reminder_days.*' => ['integer', 'min:1', 'max:365'],
                'overdue_repeat_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $expiryRequired, $validated): DocumentTypeVersion {
            $latestVersion = DocumentTypeVersion::query()
                ->where('key', $validated['key'])
                ->lockForUpdate()
                ->max('version');
            $version = is_numeric($latestVersion) ? (int) $latestVersion + 1 : 1;

            $documentType = DocumentTypeVersion::query()->create([
                'key' => $validated['key'],
                'version' => $version,
                'name' => $validated['name'],
                'expiry_required' => $expiryRequired,
                'reminder_days' => $expiryRequired ? $validated['reminder_days'] : [],
                'overdue_repeat_days' => $expiryRequired ? $validated['overdue_repeat_days'] : null,
                'published_at' => now('UTC'),
                'created_by' => $actor->id,
            ]);

            $this->recordAudit->handle(
                action: 'document_type.published',
                actor: $actor,
                auditable: $documentType,
                after: [
                    'key' => $documentType->key,
                    'version' => $version,
                    'expiry_required' => $expiryRequired,
                    'reminder_days' => $documentType->reminder_days,
                    'overdue_repeat_days' => $documentType->overdue_repeat_days,
                ],
            );

            return $documentType->refresh();
        }, 3);
    }
}
