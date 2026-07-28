<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\Client;
use App\Models\PartyRecord;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class CreatePartyRecord
{
    public function __construct(private FirmContext $context, private FeatureFlags $flags, private RecordAudit $audit) {}

    public function handle(User $actor, Client $client, mixed $reference, mixed $isCustomer, mixed $isSupplier, mixed $isActive): PartyRecord
    {
        $firmId = $this->context->firmId();
        if (! $this->flags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($client->firm_id !== $firmId) {
            throw new AuthorizationException('The client does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('create', PartyRecord::class);
        /** @var array{reference: string, isCustomer: bool, isSupplier: bool, isActive: bool} $validated */
        $validated = Validator::make(compact('reference', 'isCustomer', 'isSupplier', 'isActive'), [
            'reference' => ['required', 'string', 'max:150'],
            'isCustomer' => ['required', 'boolean'], 'isSupplier' => ['required', 'boolean'], 'isActive' => ['required', 'boolean'],
        ])->validate();
        if (! $validated['isCustomer'] && ! $validated['isSupplier']) {
            throw ValidationException::withMessages(['roles' => 'A party must be a customer, supplier or both.']);
        }

        return DB::transaction(function () use ($actor, $client, $validated): PartyRecord {
            $party = PartyRecord::query()->create([
                'client_id' => $client->id, 'reference' => trim($validated['reference']),
                'is_customer' => $validated['isCustomer'], 'is_supplier' => $validated['isSupplier'],
                'is_active' => $validated['isActive'], 'created_by' => $actor->id,
            ]);
            $this->audit->handle('party_record.created', $actor, $party, [], [
                'client_id' => $client->id, 'is_customer' => $party->is_customer,
                'is_supplier' => $party->is_supplier, 'is_active' => $party->is_active,
            ]);

            return $party->refresh();
        }, 3);
    }
}
