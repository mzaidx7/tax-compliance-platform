<?php

namespace App\Models;

use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Enums\Permission;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\FirmMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property int $user_id
 * @property FirmRole $role
 * @property FirmMembershipStatus $status
 * @property Carbon|null $joined_at
 * @property Carbon|null $suspended_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'role', 'status', 'joined_at', 'suspended_at', 'revoked_at'])]
class FirmMembership extends Pivot
{
    /** @use HasFactory<FirmMembershipFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    protected $table = 'firm_users';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->status === FirmMembershipStatus::Active
            && $this->role->allows($permission);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => FirmRole::class,
            'status' => FirmMembershipStatus::class,
            'joined_at' => 'datetime',
            'suspended_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
