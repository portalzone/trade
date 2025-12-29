<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Transaction monitoring rules
        Schema::create('transaction_monitoring_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name');
            $table->string('rule_type'); // velocity, threshold, category, geolocation, pattern
            $table->string('severity'); // low, medium, high, critical
            $table->json('conditions'); // Rule conditions as JSON
            $table->text('description');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(100); // Lower = higher priority
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });

        // Suspicious activity alerts
        Schema::create('suspicious_activity_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->bigInteger('transaction_id')->nullable(); // No FK constraint
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('rule_id')->constrained('transaction_monitoring_rules')->onDelete('cascade');
            $table->string('alert_type'); // velocity, threshold, suspicious_pattern, etc.
            $table->string('severity'); // yellow, red, critical
            $table->string('status')->default('new'); // new, investigating, resolved, false_positive
            $table->json('alert_data'); // Details of what triggered the alert
            $table->decimal('risk_score', 5, 2)->default(0); // 0-100
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('investigated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['severity', 'status']);
            $table->index('risk_score');
        });

        // SAR (Suspicious Activity Reports)
        Schema::create('suspicious_activity_reports', function (Blueprint $table) {
            $table->id();
            $table->string('sar_number')->unique(); // Auto-generated
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('alert_id')->nullable()->constrained('suspicious_activity_alerts')->onDelete('set null');
            $table->text('summary');
            $table->json('transactions'); // Array of related transaction IDs
            $table->json('alerts'); // Array of related alert IDs
            $table->decimal('total_amount', 20, 2);
            $table->string('filing_status')->default('draft'); // draft, submitted, acknowledged
            $table->timestamp('filed_at')->nullable();
            $table->string('filed_by')->nullable();
            $table->json('regulatory_response')->nullable();
            $table->timestamps();

            $table->index('sar_number');
            $table->index('filing_status');
        });

        // User risk profiles
        Schema::create('user_risk_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->decimal('overall_risk_score', 5, 2)->default(0); // 0-100
            $table->string('risk_level')->default('low'); // low, medium, high, critical
            $table->integer('velocity_score')->default(0);
            $table->integer('pattern_score')->default(0);
            $table->integer('compliance_score')->default(100);
            $table->json('risk_factors')->nullable(); // Array of risk indicators
            $table->integer('total_alerts')->default(0);
            $table->integer('resolved_alerts')->default(0);
            $table->timestamp('last_alert_at')->nullable();
            $table->timestamp('last_review_at')->nullable();
            $table->timestamps();

            $table->index('risk_level');
            $table->index('overall_risk_score');
        });

        // Alert feedback (for ML improvement)
        Schema::create('alert_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('suspicious_activity_alerts')->onDelete('cascade');
            $table->foreignId('reviewed_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_true_positive');
            $table->text('feedback_notes')->nullable();
            $table->json('improvement_suggestions')->nullable();
            $table->timestamps();

            $table->index(['alert_id', 'is_true_positive']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_feedback');
        Schema::dropIfExists('user_risk_profiles');
        Schema::dropIfExists('suspicious_activity_reports');
        Schema::dropIfExists('suspicious_activity_alerts');
        Schema::dropIfExists('transaction_monitoring_rules');
    }
};
