<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\TransactionMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionMonitoringService $monitoringService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitoringService = app(TransactionMonitoringService::class);
    }

    /** @test */
    public function it_can_get_user_risk_level()
    {
        $user = User::factory()->create();

        $risk = $this->monitoringService->getUserRiskLevel($user->id);

        $this->assertArrayHasKey('risk_level', $risk);
        $this->assertArrayHasKey('risk_score', $risk);
        $this->assertArrayHasKey('total_alerts', $risk);
    }
}
