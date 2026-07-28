<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientContactPurpose;
use App\Enums\PreferredContactChannel;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_id',
    'name',
    'position',
    'purpose',
    'preferred_channel',
    'email',
    'phone',
    'is_active',
    'created_by',
])]
final class ClientContact extends Model
{
    use BelongsToFirm, HasUlids;

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purpose' => ClientContactPurpose::class,
            'preferred_channel' => PreferredContactChannel::class,
            'is_active' => 'boolean',
        ];
    }
}
