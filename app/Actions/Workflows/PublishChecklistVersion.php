<?php

declare(strict_types=1);

namespace App\Actions\Workflows;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistVersion;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final readonly class PublishChecklistVersion
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    /** @param list<array{key: string, label: string, required?: bool}> $items */
    public function handle(User $actor, string $templateKey, string $name, array $items): ChecklistVersion
    {
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $this->firmContext->firmId())) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('create', ChecklistVersion::class);
        /** @var array{templateKey: string, name: string, items: list<array{key: string, label: string, required?: bool}>} $validated */
        $validated = Validator::make(
            ['templateKey' => $templateKey, 'name' => $name, 'items' => $items],
            [
                'templateKey' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'name' => ['required', 'string', 'max:150'],
                'items' => ['required', 'array', 'min:1', 'max:25'],
                'items.*.key' => ['required', 'string', 'max:80', 'distinct', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'items.*.label' => ['required', 'string', 'max:255'],
                'items.*.required' => ['sometimes', 'boolean'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $validated): ChecklistVersion {
            $template = ChecklistTemplate::query()->firstOrCreate(
                ['template_key' => $validated['templateKey']],
                ['name' => trim($validated['name']), 'created_by' => $actor->id],
            );
            $nextVersion = ((int) ChecklistVersion::query()
                ->whereBelongsTo($template, 'template')
                ->lockForUpdate()
                ->max('version')) + 1;
            $version = ChecklistVersion::query()->create([
                'checklist_template_id' => $template->id,
                'version' => $nextVersion,
                'status' => 'published',
                'published_by' => $actor->id,
                'published_at' => now('UTC'),
            ]);

            foreach ($validated['items'] as $index => $item) {
                ChecklistItem::query()->create([
                    'checklist_version_id' => $version->id,
                    'item_key' => $item['key'],
                    'label' => trim($item['label']),
                    'position' => $index + 1,
                    'required' => $item['required'] ?? true,
                ]);
            }

            $this->recordAudit->handle(
                action: 'checklist.version_published',
                actor: $actor,
                auditable: $version,
                after: [
                    'template_key' => $template->template_key,
                    'version' => $nextVersion,
                    'item_count' => count($validated['items']),
                ],
            );

            return $version->load(['template', 'items']);
        }, 3);
    }
}
