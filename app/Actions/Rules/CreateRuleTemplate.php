<?php

declare(strict_types=1);

namespace App\Actions\Rules;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\Obligation;
use App\Models\ObligationRuleTemplate;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final readonly class CreateRuleTemplate
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $actor,
        string $key,
        string $name,
        string $obligationType,
        string $jurisdiction,
        string $authority,
    ): ObligationRuleTemplate {
        $firmId = $this->authorize($actor);
        $normalizedKey = Str::slug(trim($key), '_');

        /** @var array{key: string, name: string, obligation_type: string, jurisdiction: string, authority: string} $validated */
        $validated = Validator::make(
            [
                'key' => $normalizedKey,
                'name' => trim($name),
                'obligation_type' => trim($obligationType),
                'jurisdiction' => trim($jurisdiction),
                'authority' => trim($authority),
            ],
            [
                'key' => [
                    'required',
                    'string',
                    'max:64',
                    'regex:/^[a-z0-9_]+$/',
                    Rule::unique('obligation_rule_templates', 'key')->where('firm_id', $firmId),
                ],
                'name' => ['required', 'string', 'max:120'],
                'obligation_type' => ['required', 'string', 'max:100'],
                'jurisdiction' => ['required', 'string', 'max:100'],
                'authority' => ['required', 'string', 'max:120'],
            ],
        )->validate();

        $template = ObligationRuleTemplate::query()->create([
            ...$validated,
            'created_by' => $actor->id,
        ]);

        $this->recordAudit->handle(
            action: 'obligation_rule.template_created',
            actor: $actor,
            auditable: $template,
            after: [
                'key' => $template->key,
                'obligation_type' => $template->obligation_type,
                'jurisdiction' => $template->jurisdiction,
                'authority' => $template->authority,
            ],
        );

        return $template->refresh();
    }

    private function authorize(User $actor): string
    {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        Gate::forUser($actor)->authorize('create', Obligation::class);

        return $firmId;
    }
}
