<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\ComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected ComplianceService $complianceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->complianceService = app(ComplianceService::class);
    }

    /** @test */
    public function it_can_generate_cbn_monthly_report()
    {
        $report = $this->complianceService->generateCBNMonthlyReport(2024, 12);

        $this->assertEquals('CBN_monthly', $report->report_type);
        $this->assertEquals('2024-12', $report->report_period);
        $this->assertEquals('draft', $report->status);
        $this->assertIsArray($report->report_data);
        $this->assertIsArray($report->statistics);
    }

    /** @test */
    public function it_can_process_data_subject_request()
    {
        $user = User::factory()->create();

        $request = $this->complianceService->processDataSubjectRequest(
            $user->id,
            'access',
            'Testing data access'
        );

        $this->assertEquals('access', $request->request_type);
        $this->assertEquals('pending', $request->status);
        $this->assertNotNull($request->request_number);
    }

    /** @test */
    public function it_can_export_user_data()
    {
        $user = User::factory()->create();

        $export = $this->complianceService->exportUserData($user->id);

        $this->assertArrayHasKey('path', $export);
        $this->assertArrayHasKey('data', $export);
        $this->assertArrayHasKey('personal_information', $export['data']);
    }
}
