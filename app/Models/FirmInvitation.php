<?php

namespace App\Models;

use App\Enums\FirmInvitationStatus;
use App\Enums\FirmRole;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\FirmInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $email
 * @property FirmRole $role
 * @property FirmInvitationStatus $status
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property int $invited_by
 * @property int|null $accepted_by
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 */
#[Fillable([
    'email',
    'role',
    'status',
    'token_hash',
    'expires_at',
    'invited_by',
    'accepted_by',
    'accepted_at',
    'revoked_at',
])]
class FirmInvitation extends Model
{
    /** @use HasFactory<FirmInvitationFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => FirmRole::class,
            'status' => FirmInvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
