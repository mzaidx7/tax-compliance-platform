<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $client_id
 * @property string|null $client_person_id
 * @property string $document_type_version_id
 * @property string|null $supersedes_client_document_id
 * @property string|null $reference_label
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property int $created_by
 * @property Carbon $recorded_at
 * @property-read Client $client
 * @property-read DocumentTypeVersion $documentTypeVersion
 * @property-read ClientDocument|null $successor
 */
#[Fillable([
    'client_id',
    'client_person_id',
    'document_type_version_id',
    'supersedes_client_document_id',
    'reference_label',
    'issued_on',
    'expires_on',
    'created_by',
    'recorded_at',
])]
final class ClientDocument extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Client document metadata is append-only.'));
        self::deleting(fn (): never => throw new LogicException('Client document metadata is append-only.'));
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<ClientPerson, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(ClientPerson::class, 'client_person_id');
    }

    /** @return BelongsTo<DocumentTypeVersion, $this> */
    public function documentTypeVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTypeVersion::class);
    }

    /** @return BelongsTo<ClientDocument, $this> */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_client_document_id');
    }

    /** @return HasOne<ClientDocument, $this> */
    public function successor(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_client_document_id');
    }

    /** @return HasMany<DocumentExpiryReminder, $this> */
    public function expiryReminders(): HasMany
    {
        return $this->hasMany(DocumentExpiryReminder::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
            'recorded_at' => 'datetime',
        ];
    }
}
