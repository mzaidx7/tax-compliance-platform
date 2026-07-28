<?php

namespace Tests\Feature\Audit;

use App\Actions\Audit\RecordAudit;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_values_are_recursively_redacted(): void
    {
        $firm = Firm::factory()->create();
        $user = User::factory()->create();

        $audit = app(FirmContext::class)->runForFirm(
            $firm,
            fn (): AuditLog => app(RecordAudit::class)->handle(
                action: 'security.checked',
                actor: $user,
                after: [
                    'name' => 'Safe',
                    'api_token' => 'never-store-this',
                    'nested' => ['password' => 'also-secret'],
                ],
            ),
        );

        $this->assertSame('Safe', $audit->after_values['name']);
        $this->assertSame('[REDACTED]', $audit->after_values['api_token']);
        $this->assertSame('[REDACTED]', $audit->after_values['nested']['password']);
    }

    public function test_audit_records_are_append_only(): void
    {
        $firm = Firm::factory()->create();
        $audit = app(FirmContext::class)->runForFirm(
            $firm,
            fn (): AuditLog => app(RecordAudit::class)->handle('record.created'),
        );

        try {
            $audit->update(['reason' => 'changed']);
            $this->fail('An audit record was updated.');
        } catch (LogicException) {
            $this->assertDatabaseHas('audit_logs', [
                'id' => $audit->id,
                'reason' => null,
            ]);
        }

        $this->expectException(LogicException::class);
        $audit->delete();
    }

    public function test_audit_queries_are_scoped_to_the_active_firm(): void
    {
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();

        app(FirmContext::class)->runForFirm(
            $firmA,
            fn (): AuditLog => app(RecordAudit::class)->handle('firm-a.event'),
        );
        app(FirmContext::class)->runForFirm(
            $firmB,
            fn (): AuditLog => app(RecordAudit::class)->handle('firm-b.event'),
        );

        app(FirmContext::class)->runForFirm($firmA, function (): void {
            $this->assertSame(['firm-a.event'], AuditLog::query()->pluck('action')->all());
        });
    }
}
