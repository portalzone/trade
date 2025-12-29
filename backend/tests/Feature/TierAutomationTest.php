<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\TierAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TierAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected TierAutomationService $tierService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tierService = app(TierAutomationService::class);
    }

    /** @test */
    public function it_can_upgrade_user_tier_manually()
    {
        $user = User::factory()->create(['kyc_tier' => 1]);
        $admin = User::factory()->create(['user_type' => 'ADMIN']);

        $tierChange = $this->tierService->manualTierChange(
            $user->id,
            2,
            'test_upgrade',
            $admin->id
        );

        $this->assertEquals(1, $tierChange->from_tier);
        $this->assertEquals(2, $tierChange->to_tier);
        $this->assertEquals('manual', $tierChange->change_type);

        $user->refresh();
        $this->assertEquals(2, $user->kyc_tier);
    }

    /** @test */
    public function it_can_downgrade_on_violation()
    {
        $user = User::factory()->create(['kyc_tier' => 2]);

        $this->tierService->autoDowngradeOnViolation(
            $user->id,
            'test_violation',
            'moderate',
            'Test description'
        );

        $user->refresh();
        $this->assertEquals(1, $user->kyc_tier);
    }

    /** @test */
    public function it_can_check_upgrade_eligibility()
    {
        $user = User::factory()->create([
            'kyc_tier' => 1,
            'nin_verified' => true,
            'bvn_verified' => true,
        ]);

        $result = $this->tierService->canUpgrade($user->id, 2);

        $this->assertTrue($result['can_upgrade']);
    }
}
