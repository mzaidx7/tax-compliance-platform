<?php

declare(strict_types=1);

namespace Tests\Feature\Tutorial;

use App\Enums\FirmRole;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Tutorial\Index as TutorialIndex;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class TutorialTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'platform.features.client_master.enabled' => true,
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.audit_viewer.enabled' => true,
        ]);
    }

    public function test_authenticated_firm_member_can_open_the_replayable_tutorial(): void
    {
        [$user, $membership] = $this->fixture();
        $this->activateFirmMembership($membership);

        $this->actingAs($user)
            ->get(route('tutorial.index'))
            ->assertOk()
            ->assertSee('Learn the compliance workflow')
            ->assertSee('Help &amp; tutorial', false)
            ->assertSee('Start with the portfolio dashboard');
    }

    public function test_tutorial_navigation_is_bounded_and_completion_is_saved(): void
    {
        [$user, $membership] = $this->fixture();
        $this->activateFirmMembership($membership);

        Livewire::actingAs($user)
            ->test(TutorialIndex::class)
            ->assertSet('step', 1)
            ->call('previousStep')
            ->assertSet('step', 1)
            ->call('nextStep')
            ->assertSet('step', 2)
            ->call('goToStep', 99)
            ->assertSet('step', TutorialIndex::STEP_COUNT)
            ->call('completeTutorial')
            ->assertSet('completed', true)
            ->assertSee('Tutorial completed');

        $this->assertNotNull($user->refresh()->tutorial_completed_at);
        $this->assertNotNull($user->tutorial_prompt_dismissed_at);
    }

    public function test_new_user_can_dismiss_the_dashboard_prompt_without_losing_tutorial_access(): void
    {
        [$user, $membership] = $this->fixture();
        $this->activateFirmMembership($membership);

        Livewire::actingAs($user)
            ->test(DashboardIndex::class)
            ->assertSet('showTutorialPrompt', true)
            ->assertSee('See the complete compliance workflow in four minutes')
            ->call('dismissTutorialPrompt')
            ->assertSet('showTutorialPrompt', false)
            ->assertDontSee('See the complete compliance workflow in four minutes');

        $this->assertNotNull($user->refresh()->tutorial_prompt_dismissed_at);

        Livewire::actingAs($user)
            ->test(TutorialIndex::class)
            ->assertSee('Learn the compliance workflow');
    }

    public function test_completed_tutorial_does_not_show_the_first_visit_prompt_again(): void
    {
        [$user, $membership] = $this->fixture();
        $this->activateFirmMembership($membership);
        $user->forceFill([
            'tutorial_prompt_dismissed_at' => now(),
            'tutorial_completed_at' => now(),
        ])->save();

        Livewire::actingAs($user)
            ->test(DashboardIndex::class)
            ->assertSet('showTutorialPrompt', false)
            ->assertDontSee('See the complete compliance workflow in four minutes');
    }

    /** @return array{User, FirmMembership} */
    private function fixture(): array
    {
        $firm = Firm::factory()->create();
        $user = User::factory()->create();
        $membership = $this->createFirmMembership($firm, $user, FirmRole::FirmAdministrator);

        return [$user, $membership];
    }
}
