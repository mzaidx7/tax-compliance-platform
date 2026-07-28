<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RecordAudit
{
    public function __construct(private FirmContext $firmContext) {}

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function handle(
        string $action,
        ?User $actor = null,
        ?Model $auditable = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
        ?string $correlationId = null,
    ): AuditLog {
        $request = app()->bound('request') ? app('request') : null;
        $ipAddress = $request instanceof Request ? $request->ip() : null;
        $userAgent = $request instanceof Request ? $request->userAgent() : null;

        $auditLog = new AuditLog([
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor === null ? null : (string) $actor->getKey(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable === null ? null : (string) $auditable->getKey(),
            'before_values' => $before === [] ? null : $this->redact($before),
            'after_values' => $after === [] ? null : $this->redact($after),
            'reason' => $reason,
            'correlation_id' => $correlationId ?? (string) Str::ulid(),
            'ip_address' => $ipAddress,
            'user_agent' => is_string($userAgent) ? Str::limit($userAgent, 512, '') : null,
        ]);

        $auditLog->forceFill(['firm_id' => $this->firmContext->firm()->getKey()]);
        $auditLog->save();

        return $auditLog->refresh();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            $redacted[$key] = is_array($value)
                ? $this->redact($value)
                : $value;
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        return Str::contains(Str::lower($key), [
            'password',
            'token',
            'secret',
            'recovery',
            'authorization',
        ]);
    }
}
