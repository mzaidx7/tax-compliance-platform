<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\InvoiceSampleFieldKey;
use App\Models\InvoiceReadinessSample;
use App\Models\InvoiceSampleField;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class AddInvoiceSampleField
{
    public function __construct(private FirmContext $context, private FeatureFlags $flags, private RecordAudit $audit) {}

    public function handle(
        User $actor,
        InvoiceReadinessSample $sample,
        mixed $fieldKey,
        mixed $suppliedValue,
        mixed $sourceReference,
    ): InvoiceSampleField {
        $firmId = $this->context->firmId();
        if (! $this->flags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($sample->firm_id !== $firmId) {
            throw new AuthorizationException('The sample does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('update', $sample);
        /** @var array{field: string, value: string, source: string} $validated */
        $validated = Validator::make(['field' => $fieldKey, 'value' => $suppliedValue, 'source' => $sourceReference], [
            'field' => ['required', Rule::enum(InvoiceSampleFieldKey::class)],
            'value' => ['required', 'string', 'max:4000'],
            'source' => ['required', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $sample, $validated): InvoiceSampleField {
            $locked = InvoiceReadinessSample::query()->lockForUpdate()->findOrFail($sample->id);
            if ($locked->fields()->where('field_key', $validated['field'])->exists()) {
                throw ValidationException::withMessages(['fieldKey' => 'This sample field is already retained.']);
            }
            $field = InvoiceSampleField::query()->create([
                'invoice_readiness_sample_id' => $locked->id,
                'field_key' => $validated['field'],
                'supplied_value' => trim($validated['value']),
                'source_reference' => trim($validated['source']),
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]);
            $this->audit->handle('invoice_sample_field.recorded', $actor, $field, [], [
                'invoice_readiness_sample_id' => $locked->id,
                'field_key' => $validated['field'],
            ]);

            return $field->refresh();
        }, 3);
    }
}
