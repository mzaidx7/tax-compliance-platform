<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Enums\ClientStatus;
use App\Enums\Feature;
use App\Models\Client;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final readonly class CreateClient
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @param  array{
     *     internalCode: mixed,
     *     legalName: mixed,
     *     tradeName?: mixed,
     *     entityType?: mixed
     * }  $input
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, array $input): Client
    {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ClientMaster, $firmId)) {
            throw new AuthorizationException('The client master is not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('create', Client::class);

        $internalCode = is_string($input['internalCode'])
            ? Str::upper(trim($input['internalCode']))
            : $input['internalCode'];

        /** @var array{internalCode: string, legalName: string, tradeName: string|null, entityType: string|null} $validated */
        $validated = Validator::make(
            [
                ...$input,
                'internalCode' => $internalCode,
                'tradeName' => $input['tradeName'] ?? null,
                'entityType' => $input['entityType'] ?? null,
            ],
            [
                'internalCode' => [
                    'required',
                    'string',
                    'max:64',
                    'regex:/^[A-Z0-9][A-Z0-9._\/-]*$/',
                    Rule::unique('clients', 'internal_code_normalized')->where(
                        static fn (Builder $query): Builder => $query->where('firm_id', $firmId),
                    ),
                ],
                'legalName' => ['required', 'string', 'max:255'],
                'tradeName' => ['nullable', 'string', 'max:255'],
                'entityType' => ['nullable', 'string', 'max:100'],
            ],
            [
                'internalCode.regex' => 'Use letters, numbers, dots, slashes, hyphens or underscores only.',
                'internalCode.unique' => 'This internal client code is already in use for the firm.',
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $validated): Client {
            $client = Client::query()->create([
                'internal_code' => $validated['internalCode'],
                'internal_code_normalized' => $validated['internalCode'],
                'legal_name' => trim($validated['legalName']),
                'trade_name' => $this->optionalText($validated['tradeName']),
                'entity_type' => $this->optionalText($validated['entityType']),
                'status' => ClientStatus::Active,
                'created_by' => $actor->id,
            ]);

            $this->recordAudit->handle(
                action: 'client.created',
                actor: $actor,
                auditable: $client,
                after: [
                    'internal_code' => $client->internal_code,
                    'legal_name' => $client->legal_name,
                    'status' => $client->status->value,
                ],
            );

            return $client->refresh();
        }, 3);
    }

    private function optionalText(?string $value): ?string
    {
        $trimmed = $value === null ? '' : trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
