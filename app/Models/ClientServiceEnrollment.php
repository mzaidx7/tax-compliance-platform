<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientService;
use App\Enums\ServiceEnrollmentStatus;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $client_id
 * @property ClientService $service
 * @property ServiceEnrollmentStatus $status
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string $responsible_membership_id
 * @property int $created_by
 * @property-read Client $client
 */
#[Fillable(['client_id', 'service', 'status', 'starts_on', 'ends_on', 'responsible_membership_id', 'created_by'])]
final class ClientServiceEnrollment extends Model
{
    use BelongsToFirm, HasUlids;

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<FirmMembership, $this> */
    public function responsibleMembership(): BelongsTo
    {
        return $this->belongsTo(FirmMembership::class, 'responsible_membership_id');
    }

    /** @return HasMany<ClientServiceEnrollmentStatusChange, $this> */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(ClientServiceEnrollmentStatusChange::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'service' => ClientService::class,
            'status' => ServiceEnrollmentStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
