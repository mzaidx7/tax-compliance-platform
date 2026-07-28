<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string $action
 * @property string|null $auditable_type
 * @property string|null $auditable_id
 * @property array<string, mixed>|null $before_values
 * @property array<string, mixed>|null $after_values
 * @property string|null $reason
 * @property string $correlation_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
#[Fillable([
    'actor_type',
    'actor_id',
    'action',
    'auditable_type',
    'auditable_id',
    'before_values',
    'after_values',
    'reason',
    'correlation_id',
    'ip_address',
    'user_agent',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Audit records are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Audit records are append-only.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }
}
