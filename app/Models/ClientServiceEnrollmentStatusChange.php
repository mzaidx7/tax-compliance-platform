<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceEnrollmentStatus;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'client_service_enrollment_id',
    'previous_status',
    'new_status',
    'effective_on',
    'changed_by',
    'reason',
    'changed_at',
])]
final class ClientServiceEnrollmentStatusChange extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Client service enrollment status history is append-only.');
        });
        self::deleting(function (): never {
            throw new LogicException('Client service enrollment status history is append-only.');
        });
    }

    /** @return BelongsTo<ClientServiceEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(ClientServiceEnrollment::class, 'client_service_enrollment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'previous_status' => ServiceEnrollmentStatus::class,
            'new_status' => ServiceEnrollmentStatus::class,
            'effective_on' => 'date',
            'changed_at' => 'datetime',
        ];
    }
}
