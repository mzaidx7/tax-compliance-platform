<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\Client;
use App\Models\InvoiceReadinessSample;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final readonly class CreateInvoiceReadinessSample
{
    public function __construct(private FirmContext $context, private FeatureFlags $flags, private RecordAudit $audit) {}

    public function handle(User $actor, Client $client, mixed $sampleReference, mixed $sourceReference): InvoiceReadinessSample
    {
        $firmId = $this->context->firmId();
        if (! $this->flags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($client->firm_id !== $firmId) {
            throw new AuthorizationException('The client does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('create', InvoiceReadinessSample::class);
        /** @var array{sample: string, source: string} $validated */
        $validated = Validator::make(['sample' => $sampleReference, 'source' => $sourceReference], [
            'sample' => ['required', 'string', 'max:150'],
            'source' => ['required', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $client, $validated): InvoiceReadinessSample {
            $sample = InvoiceReadinessSample::query()->create([
                'client_id' => $client->id,
                'sample_reference' => trim($validated['sample']),
                'source_reference' => trim($validated['source']),
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]);
            $this->audit->handle('invoice_readiness_sample.recorded', $actor, $sample, [], ['client_id' => $client->id]);

            return $sample->refresh();
        }, 3);
    }
}
