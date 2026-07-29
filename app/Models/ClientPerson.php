<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_id', 'name', 'role', 'passport_number', 'passport_expires_on', 'emirates_id_number',
    'emirates_id_expires_on', 'email', 'phone', 'is_active', 'created_by',
])]
final class ClientPerson extends Model
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
            'passport_number' => 'encrypted',
            'emirates_id_number' => 'encrypted',
            'passport_expires_on' => 'date',
            'emirates_id_expires_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
