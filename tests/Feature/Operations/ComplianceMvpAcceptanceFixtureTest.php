<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Enums\WorkItemStatus;
use App\Models\Client;
use App\Models\Firm;
use App\Models\Obligation;
use App\Models\WorkItem;
use App\Tenancy\FirmContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ComplianceMvpAcceptanceFixtureTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_command_builds_the_reconciled_200_client_fixture(): void
    {
        $this->artisan('platform:seed-compliance-acceptance', ['--synthetic-only' => true])
            ->expectsOutputToContain('The synthetic compliance MVP acceptance fixture is ready.')
            ->expectsOutputToContain('This local measurement is not a production performance claim.')
            ->assertSuccessful();

        $this->assertDatabaseCount('firms', 1);
        $this->assertDatabaseCount('clients', 200);
        $this->assertDatabaseCount('obligations', 200);
        $this->assertDatabaseCount('work_items', 200);
        $this->assertDatabaseCount('work_item_checklists', 200);
        $this->assertDatabaseCount('assignment_histories', 540);
        $this->assertSame(100, Obligation::withoutGlobalScopes()->where('obligation_type', 'like', '%VAT%')->count());
        $this->assertSame(100, Obligation::withoutGlobalScopes()->where('obligation_type', 'like', '%Corporate Tax%')->count());
        $this->assertSame(20, WorkItem::withoutGlobalScopes()->whereDoesntHave('assignmentHistories')->count());
        $this->assertSame(33, WorkItem::withoutGlobalScopes()->where('status', WorkItemStatus::UnderReview)->count());
    }

    public function test_fixture_is_fail_closed_and_tenant_scoped(): void
    {
        $this->artisan('platform:seed-compliance-acceptance', ['--synthetic-only' => true])->assertSuccessful();
        $fixtureFirm = Firm::query()->sole();
        $otherFirm = Firm::factory()->create(['slug' => 'synthetic-isolation-control']);

        app(FirmContext::class)->runForFirm($fixtureFirm, function (): void {
            $this->assertSame(200, Client::query()->count());
            $this->assertSame(200, Obligation::query()->count());
            $this->assertSame(200, WorkItem::query()->count());
        });
        app(FirmContext::class)->runForFirm($otherFirm, function (): void {
            $this->assertSame(0, Client::query()->count());
            $this->assertSame(0, Obligation::query()->count());
            $this->assertSame(0, WorkItem::query()->count());
        });

        $this->artisan('platform:seed-compliance-acceptance', ['--synthetic-only' => true])
            ->expectsOutputToContain('requires a clean database')
            ->assertFailed();
    }

    public function test_fixture_requires_explicit_synthetic_confirmation(): void
    {
        $this->artisan('platform:seed-compliance-acceptance')
            ->expectsOutputToContain('Pass --synthetic-only')
            ->assertFailed();

        $this->assertDatabaseCount('firms', 0);
    }
}
