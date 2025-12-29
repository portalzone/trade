<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RecordRetentionPolicy;

class RetentionPoliciesSeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            [
                'data_type' => 'transactions',
                'retention_years' => 10,
                'description' => 'All transaction records must be retained for 10 years per CBN regulations',
                'deletion_method' => 'archive',
                'is_active' => true,
            ],
            [
                'data_type' => 'kyc_documents',
                'retention_years' => 5,
                'description' => 'KYC documents retained for 5 years after account closure',
                'deletion_method' => 'archive',
                'is_active' => true,
            ],
            [
                'data_type' => 'audit_logs',
                'retention_years' => 7,
                'description' => 'Audit logs retained for 7 years for compliance purposes',
                'deletion_method' => 'archive',
                'is_active' => true,
            ],
            [
                'data_type' => 'communication_records',
                'retention_years' => 3,
                'description' => 'Email and SMS communications retained for 3 years',
                'deletion_method' => 'soft_delete',
                'is_active' => true,
            ],
            [
                'data_type' => 'dispute_evidence',
                'retention_years' => 7,
                'description' => 'Dispute evidence retained for 7 years',
                'deletion_method' => 'archive',
                'is_active' => true,
            ],
            [
                'data_type' => 'user_sessions',
                'retention_years' => 1,
                'description' => 'Session data retained for 1 year',
                'deletion_method' => 'hard_delete',
                'is_active' => true,
            ],
        ];

        foreach ($policies as $policy) {
            RecordRetentionPolicy::updateOrCreate(
                ['data_type' => $policy['data_type']],
                $policy
            );
        }

        $this->command->info('✅ Retention policies seeded successfully!');
    }
}
