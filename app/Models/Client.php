<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientStatus;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $internal_code
 * @property string $internal_code_normalized
 * @property string $legal_name
 * @property string|null $trade_name
 * @property string|null $entity_type
 * @property string|null $primary_email
 * @property string|null $primary_phone
 * @property string|null $vat_trn
 * @property string|null $corporate_tax_trn
 * @property string|null $vat_frequency
 * @property Carbon|null $vat_period_starts_on
 * @property Carbon|null $vat_period_ends_on
 * @property Carbon|null $corporate_tax_period_starts_on
 * @property Carbon|null $corporate_tax_period_ends_on
 * @property string|null $trade_license_number
 * @property string|null $trade_license_authority
 * @property Carbon|null $trade_license_issued_on
 * @property Carbon|null $trade_license_expires_on
 * @property string|null $passport_number
 * @property Carbon|null $passport_expires_on
 * @property string|null $emirates_id_number
 * @property Carbon|null $emirates_id_expires_on
 * @property-read Collection<int, ClientContact> $contacts
 * @property-read Collection<int, ClientPerson> $people
 * @property-read Collection<int, TaxRegistration> $taxRegistrations
 * @property ClientStatus $status
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'internal_code',
    'internal_code_normalized',
    'legal_name',
    'trade_name',
    'entity_type',
    'primary_email',
    'primary_phone',
    'vat_trn',
    'corporate_tax_trn',
    'vat_frequency',
    'vat_period_starts_on',
    'vat_period_ends_on',
    'corporate_tax_period_starts_on',
    'corporate_tax_period_ends_on',
    'trade_license_number',
    'trade_license_authority',
    'trade_license_issued_on',
    'trade_license_expires_on',
    'passport_number',
    'passport_expires_on',
    'emirates_id_number',
    'emirates_id_expires_on',
    'status',
    'created_by',
])]
final class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Obligation, $this>
     */
    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class);
    }

    /** @return HasMany<ClientContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /** @return HasMany<ClientDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    /** @return HasMany<ClientStatusChange, $this> */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(ClientStatusChange::class);
    }

    /** @return HasMany<ClientServiceEnrollment, $this> */
    public function serviceEnrollments(): HasMany
    {
        return $this->hasMany(ClientServiceEnrollment::class);
    }

    /** @return HasMany<TaxRegistration, $this> */
    public function taxRegistrations(): HasMany
    {
        return $this->hasMany(TaxRegistration::class);
    }

    /** @return HasMany<ClientPerson, $this> */
    public function people(): HasMany
    {
        return $this->hasMany(ClientPerson::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
            'trade_license_number' => 'encrypted',
            'passport_number' => 'encrypted',
            'emirates_id_number' => 'encrypted',
            'vat_period_starts_on' => 'date',
            'vat_period_ends_on' => 'date',
            'corporate_tax_period_starts_on' => 'date',
            'corporate_tax_period_ends_on' => 'date',
            'trade_license_issued_on' => 'date',
            'trade_license_expires_on' => 'date',
            'passport_expires_on' => 'date',
            'emirates_id_expires_on' => 'date',
        ];
    }
}
