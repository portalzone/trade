<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TransactionMonitoringRule;

class MonitoringRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // Velocity Rules
            [
                'rule_name' => 'High Transaction Velocity',
                'rule_type' => 'velocity',
                'severity' => 'high',
                'conditions' => [
                    'time_window' => 60, // minutes
                    'max_transactions' => 10,
                    'min_amount' => 10000,
                ],
                'description' => 'Alert when user makes more than 10 transactions in 1 hour',
                'priority' => 10,
                'is_active' => true,
            ],
            [
                'rule_name' => 'Rapid Small Transactions',
                'rule_type' => 'velocity',
                'severity' => 'medium',
                'conditions' => [
                    'time_window' => 30,
                    'max_transactions' => 20,
                    'min_amount' => 0,
                ],
                'description' => 'Alert for potential card testing with rapid small transactions',
                'priority' => 20,
                'is_active' => true,
            ],

            // Threshold Rules
            [
                'rule_name' => 'Large Single Transaction',
                'rule_type' => 'threshold',
                'severity' => 'high',
                'conditions' => [
                    'single_limit' => 1000000, // ₦1M
                ],
                'description' => 'Alert for single transactions exceeding ₦1M',
                'priority' => 5,
                'is_active' => true,
            ],
            [
                'rule_name' => 'Daily Limit Exceeded',
                'rule_type' => 'threshold',
                'severity' => 'medium',
                'conditions' => [
                    'daily_limit' => 5000000, // ₦5M
                ],
                'description' => 'Alert when daily transaction total exceeds ₦5M',
                'priority' => 15,
                'is_active' => true,
            ],

            // Pattern Rules
            [
                'rule_name' => 'New Account Large Transaction',
                'rule_type' => 'pattern',
                'severity' => 'critical',
                'conditions' => [
                    'account_age_days' => 7,
                    'min_amount' => 500000,
                ],
                'description' => 'Alert for large transactions from accounts less than 7 days old',
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'rule_name' => 'Round Number Structuring',
                'rule_type' => 'pattern',
                'severity' => 'medium',
                'conditions' => [
                    'round_amount_threshold' => 100000,
                ],
                'description' => 'Alert for potential structuring with round number transactions',
                'priority' => 30,
                'is_active' => true,
            ],
            [
                'rule_name' => 'Multiple Failed Attempts',
                'rule_type' => 'pattern',
                'severity' => 'high',
                'conditions' => [
                    'failed_threshold' => 3,
                    'time_window' => 24, // hours
                ],
                'description' => 'Alert for 3+ failed transactions in 24 hours',
                'priority' => 12,
                'is_active' => true,
            ],

            // Category Rules
            [
                'rule_name' => 'High-Risk Category Large Purchase',
                'rule_type' => 'category',
                'severity' => 'medium',
                'conditions' => [
                    'high_risk_categories' => ['Electronics', 'Jewelry', 'Luxury Goods'],
                    'amount_threshold' => 500000,
                ],
                'description' => 'Alert for large purchases in high-risk categories',
                'priority' => 25,
                'is_active' => true,
            ],
        ];

        foreach ($rules as $rule) {
            TransactionMonitoringRule::updateOrCreate(
                ['rule_name' => $rule['rule_name']],
                $rule
            );
        }

        $this->command->info('✅ Monitoring rules seeded successfully!');
    }
}
