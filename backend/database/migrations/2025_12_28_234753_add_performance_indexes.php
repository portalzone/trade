<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add only new indexes that don't already exist
        
        // Users table - new indexes
        DB::statement('CREATE INDEX IF NOT EXISTS users_kyc_status_index ON users(kyc_status)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_account_status_index ON users(account_status)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_user_type_kyc_tier_index ON users(user_type, kyc_tier)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_last_login_at_index ON users(last_login_at)');

        // Storefront products - new indexes
        DB::statement('CREATE INDEX IF NOT EXISTS storefront_products_is_active_published_at_index ON storefront_products(is_active, published_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS storefront_products_average_rating_index ON storefront_products(average_rating)');
        DB::statement('CREATE INDEX IF NOT EXISTS storefront_products_sales_count_index ON storefront_products(sales_count)');
        DB::statement('CREATE INDEX IF NOT EXISTS storefront_products_views_count_index ON storefront_products(views_count)');

        // Product reviews - new indexes
        DB::statement('CREATE INDEX IF NOT EXISTS product_reviews_product_approved_created_index ON product_reviews(product_id, is_approved, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS product_reviews_rating_index ON product_reviews(rating)');
        DB::statement('CREATE INDEX IF NOT EXISTS product_reviews_verified_purchase_index ON product_reviews(is_verified_purchase)');

        // Suspicious activity alerts - new indexes
        DB::statement('CREATE INDEX IF NOT EXISTS suspicious_activity_alerts_status_severity_created_index ON suspicious_activity_alerts(status, severity, created_at)');

        // Compliance reports - new indexes
        DB::statement('CREATE INDEX IF NOT EXISTS compliance_reports_type_period_index ON compliance_reports(report_type, period_start)');
        DB::statement('CREATE INDEX IF NOT EXISTS compliance_reports_status_created_index ON compliance_reports(status, created_at)');

        // Tier changes - new indexes
        DB::statement('CREATE INDEX IF NOT EXISTS tier_changes_change_type_created_index ON tier_changes(change_type, created_at)');

        // Notification queue - new indexes
        DB::statement('CREATE INDEX IF NOT EXISTS notification_queue_status_priority_scheduled_index ON notification_queue(status, priority, scheduled_for)');

        // Transaction monitoring rules - new indexes
        DB::statement('CREATE INDEX IF NOT EXISTS transaction_monitoring_rules_type_active_index ON transaction_monitoring_rules(rule_type, is_active)');

        // Full-text search index for products
        DB::statement('CREATE INDEX IF NOT EXISTS storefront_products_search_idx ON storefront_products USING gin(to_tsvector(\'english\', name || \' \' || COALESCE(description, \'\')))');

        echo "✅ Performance indexes created successfully\n";
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS storefront_products_search_idx');
        DB::statement('DROP INDEX IF EXISTS transaction_monitoring_rules_type_active_index');
        DB::statement('DROP INDEX IF EXISTS notification_queue_status_priority_scheduled_index');
        DB::statement('DROP INDEX IF EXISTS tier_changes_change_type_created_index');
        DB::statement('DROP INDEX IF EXISTS compliance_reports_status_created_index');
        DB::statement('DROP INDEX IF EXISTS compliance_reports_type_period_index');
        DB::statement('DROP INDEX IF EXISTS suspicious_activity_alerts_status_severity_created_index');
        DB::statement('DROP INDEX IF EXISTS product_reviews_verified_purchase_index');
        DB::statement('DROP INDEX IF EXISTS product_reviews_rating_index');
        DB::statement('DROP INDEX IF EXISTS product_reviews_product_approved_created_index');
        DB::statement('DROP INDEX IF EXISTS storefront_products_views_count_index');
        DB::statement('DROP INDEX IF EXISTS storefront_products_sales_count_index');
        DB::statement('DROP INDEX IF EXISTS storefront_products_average_rating_index');
        DB::statement('DROP INDEX IF EXISTS storefront_products_is_active_published_at_index');
        DB::statement('DROP INDEX IF EXISTS users_last_login_at_index');
        DB::statement('DROP INDEX IF EXISTS users_user_type_kyc_tier_index');
        DB::statement('DROP INDEX IF EXISTS users_account_status_index');
        DB::statement('DROP INDEX IF EXISTS users_kyc_status_index');
    }
};
